<?php

namespace App\Http\Resources;

use App\Models\SimulatedFailure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Simulated failure of an order (task 1.3, §6.3, §6.5).
 *
 * @mixin SimulatedFailure
 */
class SimulatedFailureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'error_code' => $this->error_code->value,
            'remaining' => $this->remaining,
        ];
    }
}
