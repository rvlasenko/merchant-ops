<?php

namespace Tests\Feature;

use App\Enums\AttemptResult;
use App\Enums\AttemptTrigger;
use App\Enums\SyncErrorCode;
use App\Enums\SyncStatus;
use App\Exceptions\OrderAlreadySyncedException;
use App\Models\Order;
use App\Models\SimulatedFailure;
use App\Models\SyncAttempt;
use App\Services\OrderSyncService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * Sync operation at service level (task 1.3, §5): C1–C4, C11, C12.
 */
class OrderSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    // C1
    public function test_order_without_simulation_is_synced_on_first_attempt(): void
    {
        $order = Order::factory()->create(['number' => 'MO-0001']);

        $result = $this->sync($order);

        $this->assertSame(SyncStatus::Synced, $result->sync_status);
        $this->assertSame('EXT-MO-0001', $result->external_order_id);
        $this->assertNull($result->last_error_code);
        $this->assertSame(1, $result->attempts_count);

        $attempt = $this->attempts($order)->sole();
        $this->assertSame(AttemptTrigger::Initial, $attempt->trigger);
        $this->assertSame(AttemptResult::Success, $attempt->result);
        $this->assertNull($attempt->error_code);
        $this->assertNull($attempt->error_message);
        $this->assertNull($attempt->retryable);

        $stored = $order->fresh();
        $this->assertSame(SyncStatus::Synced, $stored->sync_status);
        $this->assertSame('EXT-MO-0001', $stored->external_order_id);
        $this->assertSame(1, $stored->attempts_count);
        $this->assertTrue($stored->last_attempt_at->equalTo($attempt->created_at));
    }

    // C2
    public function test_limited_failure_is_recorded_until_it_runs_out(): void
    {
        $order = Order::factory()->create(['number' => 'MO-0002']);
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Timeout, 2)->create();

        $first = $this->sync($order);
        $this->assertSame(SyncStatus::Failed, $first->sync_status);
        $this->assertSame(SyncErrorCode::Timeout, $first->last_error_code);
        $this->assertNull($first->external_order_id);

        $this->sync($order);
        $last = $this->sync($order);

        $attempts = $this->attempts($order);
        $this->assertSame(
            [AttemptTrigger::Initial, AttemptTrigger::Retry, AttemptTrigger::Retry],
            $attempts->pluck('trigger')->all(),
        );
        $this->assertSame(
            [AttemptResult::Failed, AttemptResult::Failed, AttemptResult::Success],
            $attempts->pluck('result')->all(),
        );
        foreach ($attempts->take(2) as $failed) {
            $this->assertSame(SyncErrorCode::Timeout, $failed->error_code);
            $this->assertSame(SyncErrorCode::Timeout->message(), $failed->error_message);
            $this->assertTrue($failed->retryable);
        }

        $this->assertSame(SyncStatus::Synced, $last->sync_status);
        $this->assertSame('EXT-MO-0002', $last->external_order_id);
        $this->assertNull($last->last_error_code);
        $this->assertSame(3, $last->attempts_count);
        $this->assertSame(0, SimulatedFailure::where('order_id', $order->id)->sole()->remaining);
    }

    // C3
    public function test_unlimited_failure_repeats_until_simulation_is_removed(): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->always(SyncErrorCode::ValidationFailed)->create();

        $this->sync($order);
        $second = $this->sync($order);

        $this->assertSame(SyncStatus::Failed, $second->sync_status);
        $this->assertSame(SyncErrorCode::ValidationFailed, $second->last_error_code);
        $this->assertSame(2, $second->attempts_count);
        foreach ($this->attempts($order) as $failed) {
            $this->assertSame(AttemptResult::Failed, $failed->result);
            $this->assertSame(SyncErrorCode::ValidationFailed, $failed->error_code);
            $this->assertFalse($failed->retryable);
        }
        $this->assertNull(SimulatedFailure::where('order_id', $order->id)->sole()->remaining);

        SimulatedFailure::where('order_id', $order->id)->delete();
        $third = $this->sync($order);

        $this->assertSame(SyncStatus::Synced, $third->sync_status);
        $this->assertNull($third->last_error_code);
        $this->assertSame(3, $third->attempts_count);
        $this->assertSame(AttemptResult::Success, $this->attempts($order)->last()->result);
    }

    // C4
    public function test_synced_order_is_rejected_without_writing_anything(): void
    {
        $order = Order::factory()->synced()->create(['attempts_count' => 1, 'last_attempt_at' => now()]);
        SyncAttempt::factory()->for($order)->success()->create();
        $before = DB::table('orders')->where('id', $order->id)->first();

        try {
            $this->sync($order);
            $this->fail('Expected OrderAlreadySyncedException.');
        } catch (OrderAlreadySyncedException $e) {
            $this->assertSame('Order is already synced.', $e->getMessage());
        }

        $this->assertSame(1, SyncAttempt::where('order_id', $order->id)->count());
        $this->assertEquals($before, DB::table('orders')->where('id', $order->id)->first());
    }

    public function test_status_is_read_from_database_not_from_passed_instance(): void
    {
        $order = Order::factory()->create();
        $stale = Order::findOrFail($order->id);
        $this->sync($order);

        $this->expectException(OrderAlreadySyncedException::class);
        $this->sync($stale);
    }

    // C11
    public function test_order_summary_matches_attempt_log(): void
    {
        $plain = Order::factory()->create();
        $once = Order::factory()->create();
        $forever = Order::factory()->create();
        $twice = Order::factory()->create();
        $untouched = Order::factory()->create();
        SimulatedFailure::factory()->for($once)->limited(SyncErrorCode::Unavailable, 1)->create();
        SimulatedFailure::factory()->for($forever)->always(SyncErrorCode::Unauthorized)->create();
        SimulatedFailure::factory()->for($twice)->limited(SyncErrorCode::RateLimited, 2)->create();

        // Distinct timestamps make last_attempt_at a meaningful check.
        foreach ([$plain, $once, $forever, $once, $twice, $forever, $twice, $twice] as $order) {
            $this->travel(1)->minutes();
            $this->sync($order);
        }

        foreach ([$plain, $once, $forever, $twice, $untouched] as $order) {
            $stored = $order->fresh();
            $last = SyncAttempt::where('order_id', $order->id)
                ->orderByDesc('created_at')->orderByDesc('id')->first();

            $expectedStatus = match ($last?->result) {
                null => SyncStatus::Pending,
                AttemptResult::Success => SyncStatus::Synced,
                AttemptResult::Failed => SyncStatus::Failed,
            };

            $this->assertSame(SyncAttempt::where('order_id', $order->id)->count(), $stored->attempts_count);
            $this->assertSame($last?->created_at?->toIso8601String(), $stored->last_attempt_at?->toIso8601String());
            $this->assertSame($expectedStatus, $stored->sync_status);
            $this->assertSame($last?->error_code, $stored->last_error_code);
        }

        // Guard against a vacuous pass: the sequence must have produced every status.
        $this->assertSame(
            ['failed', 'pending', 'synced'],
            Order::distinct()->orderBy('sync_status')->pluck('sync_status')->map->value->all(),
        );
    }

    // C12
    public function test_failure_after_writes_rolls_back_everything(): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Timeout, 2)->create();
        $before = DB::table('orders')->where('id', $order->id)->first();

        // Fires on the order update, i.e. after remaining is decremented and the attempt is inserted.
        Order::updating(function () {
            throw new LogicException('Simulated crash after writes.');
        });

        try {
            $this->sync($order);
            $this->fail('Expected the simulated crash to propagate.');
        } catch (LogicException $e) {
            $this->assertSame('Simulated crash after writes.', $e->getMessage());
        }

        $this->assertSame(0, SyncAttempt::where('order_id', $order->id)->count());
        $this->assertEquals($before, DB::table('orders')->where('id', $order->id)->first());
        $this->assertSame(2, SimulatedFailure::where('order_id', $order->id)->sole()->remaining);
    }

    private function sync(Order $order): Order
    {
        return app(OrderSyncService::class)->sync($order);
    }

    /** @return Collection<int, SyncAttempt> */
    private function attempts(Order $order): Collection
    {
        return SyncAttempt::where('order_id', $order->id)->orderBy('id')->get();
    }
}
