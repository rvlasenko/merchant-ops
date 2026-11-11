<?php

namespace Tests\Feature;

use App\Enums\SyncErrorCode;
use App\Models\Order;
use App\Models\SimulatedFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Simulated failure endpoints (task 1.3, §6.5–6.6): C7–C9.
 */
class SimulatedFailureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_creates_simulation(): void
    {
        $order = Order::factory()->create();

        $this->putJson("/api/orders/{$order->id}/simulated-failure", ['error_code' => 'timeout', 'remaining' => 2])
            ->assertStatus(200)
            ->assertExactJson(['data' => ['error_code' => 'timeout', 'remaining' => 2]]);

        $simulation = SimulatedFailure::where('order_id', $order->id)->sole();
        $this->assertSame(SyncErrorCode::Timeout, $simulation->error_code);
        $this->assertSame(2, $simulation->remaining);
    }

    public function test_put_accepts_explicit_null_remaining(): void
    {
        $order = Order::factory()->create();

        $this->putJson("/api/orders/{$order->id}/simulated-failure", ['error_code' => 'unauthorized', 'remaining' => null])
            ->assertOk()
            ->assertExactJson(['data' => ['error_code' => 'unauthorized', 'remaining' => null]]);

        $this->assertNull(SimulatedFailure::where('order_id', $order->id)->sole()->remaining);
    }

    // C8
    public function test_put_replaces_existing_simulation(): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Timeout, 0)->create();

        $this->putJson("/api/orders/{$order->id}/simulated-failure", ['error_code' => 'rate_limited', 'remaining' => 3])
            ->assertOk()
            ->assertExactJson(['data' => ['error_code' => 'rate_limited', 'remaining' => 3]]);

        $simulation = SimulatedFailure::where('order_id', $order->id)->sole();
        $this->assertSame(SyncErrorCode::RateLimited, $simulation->error_code);
        $this->assertSame(3, $simulation->remaining);
    }

    public function test_put_accepts_largest_storable_remaining(): void
    {
        $order = Order::factory()->create();

        $this->putJson("/api/orders/{$order->id}/simulated-failure", ['error_code' => 'timeout', 'remaining' => 2147483647])
            ->assertOk();
    }

    // P3
    public function test_put_is_allowed_for_synced_order(): void
    {
        $order = Order::factory()->synced()->create();

        $this->putJson("/api/orders/{$order->id}/simulated-failure", ['error_code' => 'timeout', 'remaining' => 1])
            ->assertOk();
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidInput(): array
    {
        return [
            'unknown error_code' => [['error_code' => 'boom', 'remaining' => 1], 'error_code'],
            'missing error_code' => [['remaining' => 1], 'error_code'],
            'remaining = 0' => [['error_code' => 'timeout', 'remaining' => 0], 'remaining'],
            'remaining = -1' => [['error_code' => 'timeout', 'remaining' => -1], 'remaining'],
            'missing remaining' => [['error_code' => 'timeout'], 'remaining'],
            'fractional remaining' => [['error_code' => 'timeout', 'remaining' => 1.5], 'remaining'],
            // Values the integer column cannot store must be 422, not a database error.
            'boolean remaining' => [['error_code' => 'timeout', 'remaining' => true], 'remaining'],
            'string remaining' => [['error_code' => 'timeout', 'remaining' => '2'], 'remaining'],
            'remaining above int4' => [['error_code' => 'timeout', 'remaining' => 2147483648], 'remaining'],
        ];
    }

    // C7
    #[DataProvider('invalidInput')]
    public function test_invalid_put_is_rejected_and_keeps_existing_row(array $payload, string $field): void
    {
        $order = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->limited(SyncErrorCode::Unavailable, 2)->create();

        $this->putJson("/api/orders/{$order->id}/simulated-failure", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $simulation = SimulatedFailure::where('order_id', $order->id)->sole();
        $this->assertSame(SyncErrorCode::Unavailable, $simulation->error_code);
        $this->assertSame(2, $simulation->remaining);
    }

    // C9
    public function test_delete_removes_simulation(): void
    {
        $order = Order::factory()->create();
        $other = Order::factory()->create();
        SimulatedFailure::factory()->for($order)->create();
        SimulatedFailure::factory()->for($other)->create();

        $this->deleteJson("/api/orders/{$order->id}/simulated-failure")
            ->assertNoContent();

        $this->assertSame(0, SimulatedFailure::where('order_id', $order->id)->count());
        $this->assertSame(1, SimulatedFailure::where('order_id', $other->id)->count());
    }

    // C9, P4
    public function test_delete_without_simulation_returns_204(): void
    {
        $order = Order::factory()->create();

        $this->deleteJson("/api/orders/{$order->id}/simulated-failure")
            ->assertNoContent();
    }

    // C9
    public function test_unknown_or_non_numeric_order_returns_404(): void
    {
        $order = Order::factory()->create();
        $valid = ['error_code' => 'timeout', 'remaining' => 1];
        $missing = $order->id + 1000;

        // Without these checks the test would pass before the routes exist.
        $this->putJson("/api/orders/{$order->id}/simulated-failure", $valid)->assertOk();
        $this->deleteJson("/api/orders/{$order->id}/simulated-failure")->assertNoContent();

        $this->putJson("/api/orders/{$missing}/simulated-failure", $valid)->assertNotFound();
        $this->deleteJson("/api/orders/{$missing}/simulated-failure")->assertNotFound();
        $this->putJson('/api/orders/abc/simulated-failure', $valid)->assertNotFound();
        $this->deleteJson('/api/orders/abc/simulated-failure')->assertNotFound();
        $this->putJson('/api/orders/99999999999999999999/simulated-failure', $valid)->assertNotFound();
        $this->deleteJson('/api/orders/99999999999999999999/simulated-failure')->assertNotFound();
        $this->assertSame(0, SimulatedFailure::count());
    }
}
