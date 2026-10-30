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
        $this->insertOrder(['number' => 'MO-0001']);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertOrder(['number' => 'MO-0001']);
    }

    public function test_second_simulation_for_the_same_order_is_rejected(): void
    {
        $orderId = $this->insertOrder();
        $this->insertSimulation($orderId);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertSimulation($orderId);
    }

    public function test_unknown_sync_status_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        $this->insertOrder(['sync_status' => 'unknown']);
    }

    public function test_negative_total_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        $this->insertOrder(['total_cents' => -1]);
    }

    public function test_lowercase_currency_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        $this->insertOrder(['currency' => 'eur']);
    }

    public function test_negative_remaining_is_rejected(): void
    {
        $orderId = $this->insertOrder();

        $this->expectException(QueryException::class);
        $this->insertSimulation($orderId, ['remaining' => -1]);
    }

    public function test_successful_attempt_with_error_is_rejected(): void
    {
        $orderId = $this->insertOrder();

        $this->expectException(QueryException::class);
        $this->insertAttempt($orderId, ['result' => 'success', 'error_code' => 'timeout']);
    }

    public function test_failed_attempt_without_error_is_rejected(): void
    {
        $orderId = $this->insertOrder();

        $this->expectException(QueryException::class);
        $this->insertAttempt($orderId, ['result' => 'failed']);
    }

    public function test_attempt_for_missing_order_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        $this->insertAttempt(999999);
    }

    public function test_deleting_order_deletes_attempts_and_simulation(): void
    {
        $orderId = $this->insertOrder();
        $otherOrderId = $this->insertOrder(['number' => 'MO-0002']);
        $this->insertAttempt($orderId);
        $this->insertAttempt($orderId, $this->failedAttempt(SyncErrorCode::Timeout));
        $this->insertAttempt($otherOrderId);
        $this->insertSimulation($orderId);

        DB::table('orders')->where('id', $orderId)->delete();

        $this->assertSame(0, DB::table('sync_attempts')->where('order_id', $orderId)->count());
        $this->assertSame(0, DB::table('simulated_failures')->where('order_id', $orderId)->count());
        $this->assertSame(1, DB::table('sync_attempts')->where('order_id', $otherOrderId)->count());
    }

    public function test_every_enum_value_is_accepted_by_its_column(): void
    {
        foreach (SyncStatus::cases() as $i => $status) {
            $this->insertOrder(['number' => "MO-S{$i}", 'sync_status' => $status->value]);
        }

        $orderId = $this->insertOrder();
        foreach (AttemptTrigger::cases() as $trigger) {
            $this->insertAttempt($orderId, ['trigger' => $trigger->value]);
        }
        $this->insertAttempt($orderId, ['result' => AttemptResult::Success->value]);

        foreach (SyncErrorCode::cases() as $i => $code) {
            $codeOrderId = $this->insertOrder(['number' => "MO-E{$i}", 'last_error_code' => $code->value]);
            $this->insertAttempt($codeOrderId, $this->failedAttempt($code));
            $this->insertSimulation($codeOrderId, ['error_code' => $code->value]);
        }

        $this->assertSame(count(SyncErrorCode::cases()), DB::table('simulated_failures')->count());
    }

    public function test_models_cast_enum_columns(): void
    {
        $order = $this->createOrder(['last_error_code' => SyncErrorCode::Timeout]);
        $order->syncAttempts()->create($this->failedAttempt(SyncErrorCode::Timeout) + [
            'trigger' => AttemptTrigger::Initial,
        ]);
        $order->simulatedFailure()->create(['error_code' => SyncErrorCode::Unavailable, 'remaining' => 2]);

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
        $attempt = $this->createOrder()->syncAttempts()->create([
            'trigger' => AttemptTrigger::Initial,
            'result' => AttemptResult::Success,
        ]);

        $this->assertNotNull($attempt->created_at);
        $this->assertArrayNotHasKey('updated_at', $attempt->getAttributes());
    }

    public function test_model_relations_return_expected_types(): void
    {
        $order = $this->createOrder();
        $withoutSimulation = $this->createOrder(['number' => 'MO-0002']);
        $order->syncAttempts()->create(['trigger' => AttemptTrigger::Initial, 'result' => AttemptResult::Success]);
        $order->syncAttempts()->create(['trigger' => AttemptTrigger::Retry, 'result' => AttemptResult::Success]);
        $order->simulatedFailure()->create(['error_code' => SyncErrorCode::Timeout]);

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
        $order = $this->createOrder();
        $order->syncAttempts()->create(['trigger' => AttemptTrigger::Initial, 'result' => AttemptResult::Success]);
        $order->syncAttempts()->create($this->failedAttempt(SyncErrorCode::Timeout) + [
            'trigger' => AttemptTrigger::Retry,
        ]);
        $order->simulatedFailure()->create(['error_code' => SyncErrorCode::Timeout]);

        $order->delete();

        $this->assertSame(0, SyncAttempt::where('order_id', $order->id)->count());
        $this->assertSame(0, SimulatedFailure::where('order_id', $order->id)->count());
    }

    private function createOrder(array $overrides = []): Order
    {
        return Order::create($overrides + [
            'number' => 'MO-0001',
            'customer_name' => 'Ada Lovelace',
            'total_cents' => 1099,
            'currency' => 'EUR',
        ]);
    }

    private function insertOrder(array $overrides = []): int
    {
        return DB::table('orders')->insertGetId($overrides + [
            'number' => 'MO-0001',
            'customer_name' => 'Ada Lovelace',
            'total_cents' => 1099,
            'currency' => 'EUR',
        ]);
    }

    private function insertAttempt(int $orderId, array $overrides = []): void
    {
        DB::table('sync_attempts')->insert($overrides + [
            'order_id' => $orderId,
            'trigger' => AttemptTrigger::Initial->value,
            'result' => AttemptResult::Success->value,
        ]);
    }

    private function insertSimulation(int $orderId, array $overrides = []): void
    {
        DB::table('simulated_failures')->insert($overrides + [
            'order_id' => $orderId,
            'error_code' => SyncErrorCode::Timeout->value,
            'remaining' => null,
        ]);
    }

    private function failedAttempt(SyncErrorCode $code): array
    {
        return [
            'result' => AttemptResult::Failed->value,
            'error_code' => $code->value,
            'error_message' => $code->message(),
            'retryable' => $code->retryable(),
        ];
    }
}
