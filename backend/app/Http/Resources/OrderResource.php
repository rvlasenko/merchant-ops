<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order fields (task 1.3, §6.1). Simulation and attempts are included only
 * when loaded, i.e. in the detail view (§6.3).
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_name' => $this->customer_name,
            'total_cents' => $this->total_cents,
            'currency' => $this->currency,
            'sync_status' => $this->sync_status->value,
            'external_order_id' => $this->external_order_id,
            'last_error_code' => $this->last_error_code?->value,
            // Computed from the current classification, not stored: "will a retry help now".
            'last_error_retryable' => $this->last_error_code?->retryable(),
            'attempts_count' => $this->attempts_count,
            'last_attempt_at' => $this->last_attempt_at?->toIso8601String(),
            'simulated_failure' => $this->whenLoaded(
                'simulatedFailure',
                fn () => new SimulatedFailureResource($this->simulatedFailure),
            ),
            'attempts' => SyncAttemptResource::collection($this->whenLoaded('syncAttempts')),
        ];
    }
}
