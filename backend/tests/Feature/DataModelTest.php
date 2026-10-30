<?php

namespace Tests\Feature;

use App\Enums\AttemptResult;
use App\Enums\AttemptTrigger;
use App\Enums\SyncErrorCode;
use App\Enums\SyncStatus;
use App\Models\Order;
use App\Models\SimulatedFailure;
use App\Models\SyncAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_order_number_is_rejected(): void
    {
        Order::factory()->create(['number' => 'MO-0001']);

        $this->expectException(UniqueConstraintViolationException::class);
        Order::factory()->create(['number' => 'MO-0001']);
    }

    public function test_factory_generates_unique_order_numbers(): void
    {
        $orders = Order::factory()->count(3)->create();

        $this->assertCount(3, $orders->pluck('number')->unique());
    }

    public function test_second_simulation_for_the_same_order_is_rejected(): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->create();

        $this->expectException(UniqueConstraintViolationException::class);
        SimulatedFailure::factory()->for($order)->create();
    }

    public function test_unknown_sync_status_is_rejected(): void
    {
        // Raw insert: the enum cast would reject the value before it reaches the database.
        $this->expectException(QueryException::class);
        DB::table('orders')->insert([
            'number' => 'MO-0001',
            'customer_name' => 'Ada Lovelace',
            'total_cents' => 1099,
            'currency' => 'EUR',
            'sync_status' => 'unknown',
        ]);
    }

    public function test_negative_total_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        Order::factory()->create(['total_cents' => -1]);
    }

    public function test_lowercase_currency_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        Order::factory()->create(['currency' => 'eur']);
    }

    public function test_negative_remaining_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->expectException(QueryException::class);
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Timeout, -1)->create();
    }

    public function test_successful_attempt_with_error_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->expectException(QueryException::class);
        SyncAttempt::factory()->for($order)->success()->create(['error_code' => SyncErrorCode::Timeout]);
    }

    public function test_failed_attempt_without_error_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->expectException(QueryException::class);
        SyncAttempt::factory()->for($order)->create(['result' => AttemptResult::Failed]);
    }

    public function test_attempt_for_missing_order_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        SyncAttempt::factory()->create(['order_id' => 999999]);
    }

    public function test_deleting_order_deletes_attempts_and_simulation(): void
    {
        $order = Order::factory()->create();
        $otherOrder = Order::factory()->create();
        SyncAttempt::factory()->for($order)->success()->create();
        SyncAttempt::factory()->for($order)->failed(SyncErrorCode::Timeout)->create();
        SyncAttempt::factory()->for($otherOrder)->success()->create();
        SimulatedFailure::factory()->for($order)->create();

        // Delete through the query builder to check the database-level cascade.
        DB::table('orders')->where('id', $order->id)->delete();

        $this->assertSame(0, DB::table('sync_attempts')->where('order_id', $order->id)->count());
        $this->assertSame(0, DB::table('simulated_failures')->where('order_id', $order->id)->count());
        $this->assertSame(1, DB::table('sync_attempts')->where('order_id', $otherOrder->id)->count());
    }

    public function test_every_enum_value_is_accepted_by_its_column(): void
    {
        foreach (SyncStatus::cases() as $status) {
            Order::factory()->create(['sync_status' => $status]);
        }

        $order = Order::factory()->create();
        foreach (AttemptTrigger::cases() as $trigger) {
            SyncAttempt::factory()->for($order)->success()->create(['trigger' => $trigger]);
        }

        foreach (SyncErrorCode::cases() as $code) {
            $codeOrder = Order::factory()->create(['last_error_code' => $code]);
            SyncAttempt::factory()->for($codeOrder)->failed($code)->create();
            SimulatedFailure::factory()->for($codeOrder)->always($code)->create();
        }

        $this->assertSame(count(SyncErrorCode::cases()), DB::table('simulated_failures')->count());
    }

    public function test_models_cast_enum_columns(): void
    {
        $order = Order::factory()->create(['last_error_code' => SyncErrorCode::Timeout]);
        SyncAttempt::factory()->for($order)->failed(SyncErrorCode::Timeout)->create();
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Unavailable, 2)->create();

        $order = Order::findOrFail($order->id);
        $attempt = SyncAttempt::firstOrFail();
        $simulation = SimulatedFailure::firstOrFail();

        $this->assertSame(SyncStatus::Pending, $order->sync_status);
        $this->assertSame(SyncErrorCode::Timeout, $order->last_error_code);
        $this->assertSame(0, $order->attempts_count);
        $this->assertSame(AttemptTrigger::Initial, $attempt->trigger);
        $this->assertSame(AttemptResult::Failed, $attempt->result);
        $this->assertSame(SyncErrorCode::Timeout, $attempt->error_code);
        $this->assertTrue($attempt->retryable);
        $this->assertInstanceOf(CarbonImmutable::class, $attempt->created_at);
        $this->assertSame(SyncErrorCode::Unavailable, $simulation->error_code);
        $this->assertSame(2, $simulation->remaining);
    }

    public function test_sync_attempt_has_no_updated_at(): void
    {
        $attempt = SyncAttempt::factory()->create();

        $this->assertNotNull($attempt->created_at);
        $this->assertArrayNotHasKey('updated_at', $attempt->getAttributes());
    }

    public function test_model_relations_return_expected_types(): void
    {
        $order = Order::factory()->create();
        $withoutSimulation = Order::factory()->create();
        SyncAttempt::factory()->for($order)->success()->create();
        SyncAttempt::factory()->for($order)->success()->create(['trigger' => AttemptTrigger::Retry]);
        SimulatedFailure::factory()->for($order)->create();

        $order->refresh();

        $this->assertInstanceOf(Collection::class, $order->syncAttempts);
        $this->assertCount(2, $order->syncAttempts);
        $this->assertContainsOnlyInstancesOf(SyncAttempt::class, $order->syncAttempts);
        $this->assertInstanceOf(SimulatedFailure::class, $order->simulatedFailure);
        $this->assertNull($withoutSimulation->simulatedFailure);
        $this->assertTrue($order->syncAttempts->first()->order->is($order));
        $this->assertTrue($order->simulatedFailure->order->is($order));
    }

    public function test_deleting_order_model_deletes_attempts_and_simulation(): void
    {
        $order = Order::factory()->create();
        SyncAttempt::factory()->for($order)->success()->create();
        SyncAttempt::factory()->for($order)->failed(SyncErrorCode::Timeout)->create(['trigger' => AttemptTrigger::Retry]);
        SimulatedFailure::factory()->for($order)->create();

        $order->delete();

        $this->assertSame(0, SyncAttempt::where('order_id', $order->id)->count());
        $this->assertSame(0, SimulatedFailure::where('order_id', $order->id)->count());
    }
}
