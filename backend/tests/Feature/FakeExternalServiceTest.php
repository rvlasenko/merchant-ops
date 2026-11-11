<?php

namespace Tests\Feature;

use App\Enums\SyncErrorCode;
use App\ExternalService\FakeExternalService;
use App\Models\Order;
use App\Models\SimulatedFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Decision table of the simulated external service (task 1.3, §4, C13).
 * A feature test, not a unit test: the service reads and changes simulated_failures.
 */
class FakeExternalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_without_simulation_succeeds_with_id_derived_from_number(): void
    {
        $order = Order::factory()->create(['number' => 'MO-0042']);

        $response = (new FakeExternalService)->send($order);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('EXT-MO-0042', $response->externalOrderId);
        $this->assertNull($response->errorCode);
        $this->assertSame(0, SimulatedFailure::count());
    }

    public function test_unlimited_simulation_fails_and_stays_unchanged(): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->always(SyncErrorCode::ValidationFailed)->create();
        $service = new FakeExternalService;

        $first = $service->send($order);
        $second = $service->send($order);

        foreach ([$first, $second] as $response) {
            $this->assertFalse($response->isSuccess());
            $this->assertSame(SyncErrorCode::ValidationFailed, $response->errorCode);
            $this->assertNull($response->externalOrderId);
        }
        $this->assertNull(SimulatedFailure::sole()->remaining);
    }

    public function test_limited_simulation_fails_and_decrements_remaining(): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Timeout, 2)->create();

        $response = (new FakeExternalService)->send($order);

        $this->assertFalse($response->isSuccess());
        $this->assertSame(SyncErrorCode::Timeout, $response->errorCode);
        $this->assertNull($response->externalOrderId);
        $this->assertSame(1, SimulatedFailure::sole()->remaining);
    }

    public function test_last_limited_failure_leaves_used_up_row_in_place(): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::RateLimited, 1)->create();

        $response = (new FakeExternalService)->send($order);

        $this->assertSame(SyncErrorCode::RateLimited, $response->errorCode);
        $this->assertSame(0, SimulatedFailure::sole()->remaining);
    }

    public function test_used_up_simulation_succeeds_and_stays_unchanged(): void
    {
        $order = Order::factory()->create(['number' => 'MO-0043']);
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Unavailable, 0)->create();

        $response = (new FakeExternalService)->send($order);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('EXT-MO-0043', $response->externalOrderId);
        $simulation = SimulatedFailure::sole();
        $this->assertSame(0, $simulation->remaining);
        $this->assertSame(SyncErrorCode::Unavailable, $simulation->error_code);
    }

    public function test_limited_simulation_runs_out_after_configured_failures(): void
    {
        $order = Order::factory()->create(['number' => 'MO-0044']);
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Timeout, 2)->create();
        $service = new FakeExternalService;

        $outcomes = [];
        for ($i = 0; $i < 3; $i++) {
            $outcomes[] = $service->send($order)->externalOrderId ?? 'failed';
        }

        $this->assertSame(['failed', 'failed', 'EXT-MO-0044'], $outcomes);
    }

    public function test_simulation_of_another_order_has_no_effect(): void
    {
        $order = Order::factory()->create(['number' => 'MO-0045']);
        $other = Order::factory()->create();
        SimulatedFailure::factory()->for($other)->limited(SyncErrorCode::Timeout, 1)->create();

        $response = (new FakeExternalService)->send($order);

        $this->assertSame('EXT-MO-0045', $response->externalOrderId);
        $this->assertSame(1, SimulatedFailure::sole()->remaining);
    }

    public function test_simulation_changed_after_order_was_loaded_is_respected(): void
    {
        $order = Order::factory()->create();
        $order->load('simulatedFailure');
        SimulatedFailure::factory()->for($order)->always(SyncErrorCode::Unauthorized)->create();

        $response = (new FakeExternalService)->send($order);

        $this->assertSame(SyncErrorCode::Unauthorized, $response->errorCode);
    }
}
