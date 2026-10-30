<?php

namespace Database\Factories;

use App\Enums\AttemptResult;
use App\Enums\AttemptTrigger;
use App\Enums\SyncErrorCode;
use App\Models\Order;
use App\Models\SyncAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncAttempt>
 */
class SyncAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'trigger' => AttemptTrigger::Initial,
            'result' => AttemptResult::Success,
            'error_code' => null,
            'error_message' => null,
            'retryable' => null,
        ];
    }

    public function success(): static
    {
        return $this->state([
            'result' => AttemptResult::Success,
            'error_code' => null,
            'error_message' => null,
            'retryable' => null,
        ]);
    }

    public function failed(SyncErrorCode $code): static
    {
        return $this->state([
            'result' => AttemptResult::Failed,
            'error_code' => $code,
            'error_message' => $code->message(),
            'retryable' => $code->retryable(),
        ]);
    }
}
