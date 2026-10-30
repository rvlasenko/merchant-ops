<?php

namespace Database\Factories;

use App\Enums\SyncStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Counter for unique order numbers. It is not reset between tests,
     * so tests must not rely on the generated number or total.
     */
    private static int $counter = 0;

    public function definition(): array
    {
        $n = ++self::$counter;

        return [
            'number' => sprintf('MO-%04d', $n),
            'customer_name' => "Customer {$n}",
            'total_cents' => 1000 * $n + 99,
            'currency' => 'EUR',
            'sync_status' => SyncStatus::Pending,
            'marketplace_order_id' => null,
            'last_error_code' => null,
            'attempts_count' => 0,
            'last_attempt_at' => null,
        ];
    }
}
