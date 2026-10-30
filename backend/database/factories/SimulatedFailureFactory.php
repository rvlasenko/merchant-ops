<?php

namespace Database\Factories;

use App\Enums\SyncErrorCode;
use App\Models\Order;
use App\Models\SimulatedFailure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimulatedFailure>
 */
class SimulatedFailureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'error_code' => SyncErrorCode::Timeout,
            'remaining' => null,
        ];
    }

    public function always(SyncErrorCode $code): static
    {
        return $this->state(['error_code' => $code, 'remaining' => null]);
    }

    // Not named times(): Factory::times() already exists.
    public function limited(SyncErrorCode $code, int $remaining): static
    {
        return $this->state(['error_code' => $code, 'remaining' => $remaining]);
    }
}
