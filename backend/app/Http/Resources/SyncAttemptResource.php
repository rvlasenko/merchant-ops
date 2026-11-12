<?php

namespace App\Http\Resources;

use App\Models\SyncAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the attempt log (task 1.3, §6.3). Message and retryable are
 * the snapshots taken at attempt time.
 *
 * @mixin SyncAttempt
 */
class SyncAttemptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trigger' => $this->trigger->value,
            'result' => $this->result->value,
            'error_code' => $this->error_code?->value,
            'error_message' => $this->error_message,
            'retryable' => $this->retryable,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
