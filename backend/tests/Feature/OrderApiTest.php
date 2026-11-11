<?php

namespace Tests\Feature;

use App\Enums\SyncErrorCode;
use App\Enums\SyncStatus;
use App\Exceptions\OrderAlreadySyncedException;
use App\Models\Order;
use App\Models\SimulatedFailure;
use App\Models\SyncAttempt;
use App\Services\OrderSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

/**
 * Read API (task 1.3, §6.1–6.3): C5, C6, 404 part of C9.
 */
class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00'));
    }

    public function test_list_returns_order_fields(): void
    {
        $timeout = $this->failedOrder(SyncErrorCode::Timeout, ['number' => 'MO-0003', 'customer_name' => 'Grace Hopper', 'total_cents' => 3099]);
        $this->failedOrder(SyncErrorCode::Unauthorized);
        Order::factory()->create();

        $response = $this->getJson('/api/orders');

        $response->assertOk();
        $this->assertSame([
            'id' => $timeout->id,
            'number' => 'MO-0003',
            'customer_name' => 'Grace Hopper',
            'total_cents' => 3099,
            'currency' => 'EUR',
            'sync_status' => 'failed',
            'external_order_id' => null,
            'last_error_code' => 'timeout',
            'last_error_retryable' => true,
            'attempts_count' => 1,
            'last_attempt_at' => '2026-11-10T12:00:00+00:00',
        ], $response->json('data.0'));
        $this->assertFalse($response->json('data.1.last_error_retryable'));
        $this->assertNull($response->json('data.2.last_error_retryable'));
        $this->assertNull($response->json('data.2.last_attempt_at'));
    }

    public function test_list_is_paginated_by_15_in_id_order(): void
    {
        Order::factory()->count(20)->create();
        $ids = Order::orderBy('id')->pluck('id')->all();

        $first = $this->getJson('/api/orders');
        $second = $this->getJson('/api/orders?page=2');

        $first->assertOk()->assertJsonPath('meta.total', 20)->assertJsonPath('meta.per_page', 15)->assertJsonPath('meta.last_page', 2);
        $this->assertSame(array_slice($ids, 0, 15), $first->json('data.*.id'));
        $this->assertSame(array_slice($ids, 15), $second->json('data.*.id'));
    }

    // C6
    public function test_list_filters_by_status_and_keeps_filter_in_links(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->failedOrder(SyncErrorCode::Timeout);
        }
        Order::factory()->count(2)->create();
        Order::factory()->synced()->create();

        $first = $this->getJson('/api/orders?sync_status=failed');

        $first->assertOk()->assertJsonPath('meta.total', 16);
        $this->assertSame(array_fill(0, 15, 'failed'), $first->json('data.*.sync_status'));

        $next = $first->json('links.next');
        $this->assertStringContainsString('sync_status=failed', $next);
        $second = $this->getJson($next);
        $this->assertSame(['failed'], $second->json('data.*.sync_status'));
    }

    // C6
    public function test_unknown_status_filter_is_rejected(): void
    {
        $this->getJson('/api/orders?sync_status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sync_status');
    }

    public function test_list_does_not_include_attempts_or_simulation(): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->create();
        SyncAttempt::factory()->for($order)->create();

        $item = $this->getJson('/api/orders')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('attempts', $item);
        $this->assertArrayNotHasKey('simulated_failure', $item);
    }

    // C5
    public function test_show_returns_order_with_simulation_and_attempts_newest_first(): void
    {
        $order = Order::factory()->create(['number' => 'MO-0003', 'customer_name' => 'Grace Hopper', 'total_cents' => 3099]);
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Timeout, 2)->create();
        $service = app(OrderSyncService::class);
        foreach (range(1, 3) as $minute) {
            $this->travelTo(CarbonImmutable::parse("2026-11-10 12:0{$minute}:00"));
            $service->sync($order);
        }
        $ids = SyncAttempt::where('order_id', $order->id)->orderBy('id')->pluck('id')->all();

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertOk();
        $failed = fn (int $id, string $trigger, string $time) => [
            'id' => $id,
            'trigger' => $trigger,
            'result' => 'failed',
            'error_code' => 'timeout',
            'error_message' => 'External service did not respond in time.',
            'retryable' => true,
            'created_at' => $time,
        ];
        $this->assertSame([
            'id' => $order->id,
            'number' => 'MO-0003',
            'customer_name' => 'Grace Hopper',
            'total_cents' => 3099,
            'currency' => 'EUR',
            'sync_status' => 'synced',
            'external_order_id' => 'EXT-MO-0003',
            'last_error_code' => null,
            'last_error_retryable' => null,
            'attempts_count' => 3,
            'last_attempt_at' => '2026-11-10T12:03:00+00:00',
            'simulated_failure' => ['error_code' => 'timeout', 'remaining' => 0],
            'attempts' => [
                [
                    'id' => $ids[2],
                    'trigger' => 'retry',
                    'result' => 'success',
                    'error_code' => null,
                    'error_message' => null,
                    'retryable' => null,
                    'created_at' => '2026-11-10T12:03:00+00:00',
                ],
                $failed($ids[1], 'retry', '2026-11-10T12:02:00+00:00'),
                $failed($ids[0], 'initial', '2026-11-10T12:01:00+00:00'),
            ],
        ], $response->json('data'));
    }

    public function test_show_without_simulation_and_attempts(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.simulated_failure', null)
            ->assertJsonPath('data.attempts', []);
    }

    public function test_attempts_with_equal_timestamps_are_ordered_by_id_desc(): void
    {
        $order = Order::factory()->create();
        $ids = SyncAttempt::factory()->count(3)->for($order)->create()->pluck('id')->all();

        $response = $this->getJson("/api/orders/{$order->id}");

        $this->assertSame(array_reverse($ids), $response->json('data.attempts.*.id'));
    }

    public function test_attempts_are_ordered_by_time_before_id(): void
    {
        $order = Order::factory()->create();
        $later = SyncAttempt::factory()->for($order)->create(['created_at' => '2026-11-10 12:05:00']);
        $earlier = SyncAttempt::factory()->for($order)->create(['created_at' => '2026-11-10 12:01:00']);

        $response = $this->getJson("/api/orders/{$order->id}");

        $this->assertSame([$later->id, $earlier->id], $response->json('data.attempts.*.id'));
    }

    // C9 (read part)
    public function test_unknown_or_non_numeric_order_id_returns_404(): void
    {
        $order = Order::factory()->create();

        // Without this check the test would pass before the route exists.
        $this->getJson("/api/orders/{$order->id}")->assertOk();

        $this->getJson('/api/orders/'.($order->id + 1000))->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/api/orders/abc')->assertNotFound()->assertJsonStructure(['message']);
        // Beyond bigint: must not reach PostgreSQL, which would reject it with 500.
        $this->getJson('/api/orders/99999999999999999999')->assertNotFound();
    }

    // C1 over HTTP
    public function test_sync_of_order_without_simulation_returns_synced_order(): void
    {
        $order = Order::factory()->create(['number' => 'MO-0001']);

        $response = $this->postJson("/api/orders/{$order->id}/sync");

        $response->assertOk()
            ->assertJsonPath('data.sync_status', 'synced')
            ->assertJsonPath('data.external_order_id', 'EXT-MO-0001')
            ->assertJsonPath('data.last_error_code', null)
            ->assertJsonPath('data.attempts_count', 1)
            ->assertJsonPath('data.last_attempt_at', '2026-11-10T12:00:00+00:00')
            ->assertJsonCount(1, 'data.attempts')
            ->assertJsonPath('data.attempts.0.trigger', 'initial')
            ->assertJsonPath('data.attempts.0.result', 'success');
        $this->assertSame(1, SyncAttempt::where('order_id', $order->id)->count());
    }

    // C2 over HTTP: a failed attempt is a normal outcome, still 200 (D5)
    public function test_sync_with_limited_failure_reports_each_attempt(): void
    {
        $order = Order::factory()->create(['number' => 'MO-0002']);
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Timeout, 2)->create();

        $first = $this->postJson("/api/orders/{$order->id}/sync");
        $first->assertOk()
            ->assertJsonPath('data.sync_status', 'failed')
            ->assertJsonPath('data.last_error_code', 'timeout')
            ->assertJsonPath('data.last_error_retryable', true)
            ->assertJsonPath('data.attempts.0.result', 'failed')
            ->assertJsonPath('data.attempts.0.retryable', true)
            ->assertJsonPath('data.simulated_failure.remaining', 1);

        $this->postJson("/api/orders/{$order->id}/sync")->assertOk();
        $last = $this->postJson("/api/orders/{$order->id}/sync");

        $last->assertOk()
            ->assertJsonPath('data.sync_status', 'synced')
            ->assertJsonPath('data.attempts_count', 3)
            ->assertJsonPath('data.simulated_failure', ['error_code' => 'timeout', 'remaining' => 0]);
        $this->assertSame(['retry', 'retry', 'initial'], $last->json('data.attempts.*.trigger'));
        $this->assertSame(['success', 'failed', 'failed'], $last->json('data.attempts.*.result'));
    }

    // C3 over HTTP
    public function test_unlimited_failure_is_fixed_by_deleting_simulation(): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->always(SyncErrorCode::ValidationFailed)->create();

        $this->postJson("/api/orders/{$order->id}/sync")->assertOk();
        $this->postJson("/api/orders/{$order->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.sync_status', 'failed')
            ->assertJsonPath('data.last_error_retryable', false)
            ->assertJsonPath('data.attempts.0.retryable', false)
            ->assertJsonPath('data.simulated_failure.remaining', null);

        $this->deleteJson("/api/orders/{$order->id}/simulated-failure")->assertNoContent();

        $this->postJson("/api/orders/{$order->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.sync_status', 'synced')
            ->assertJsonPath('data.attempts_count', 3)
            ->assertJsonPath('data.simulated_failure', null);
    }

    // C4 over HTTP
    public function test_sync_of_synced_order_returns_409_and_changes_nothing(): void
    {
        $order = Order::factory()->synced()->create(['attempts_count' => 1, 'last_attempt_at' => now()]);
        SyncAttempt::factory()->for($order)->success()->create();
        $before = $order->fresh()->getAttributes();

        $this->postJson("/api/orders/{$order->id}/sync")
            ->assertConflict()
            ->assertExactJson(['message' => 'Order is already synced.']);

        $this->assertSame(1, SyncAttempt::where('order_id', $order->id)->count());
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    // C9 (sync part)
    public function test_sync_of_unknown_or_non_numeric_order_returns_404(): void
    {
        $order = Order::factory()->create();

        // Without this check the test would pass before the route exists.
        $this->postJson("/api/orders/{$order->id}/sync")->assertOk();

        $this->postJson('/api/orders/'.($order->id + 1000).'/sync')->assertNotFound();
        $this->postJson('/api/orders/abc/sync')->assertNotFound();
        $this->postJson('/api/orders/99999999999999999999/sync')->assertNotFound();
    }

    public function test_already_synced_is_not_reported_as_error(): void
    {
        $handler = app(ExceptionHandler::class);

        $this->assertFalse($handler->shouldReport(new OrderAlreadySyncedException));
    }

    public function test_empty_status_filter_means_no_filter(): void
    {
        Order::factory()->create();
        $this->failedOrder(SyncErrorCode::Timeout);

        $this->getJson('/api/orders?sync_status=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    private function failedOrder(SyncErrorCode $code, array $attributes = []): Order
    {
        return Order::factory()->create([
            'sync_status' => SyncStatus::Failed,
            'last_error_code' => $code,
            'attempts_count' => 1,
            'last_attempt_at' => now(),
            ...$attributes,
        ]);
    }
}
