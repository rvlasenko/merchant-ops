<?php

namespace App\Http\Requests;

use App\Enums\SyncStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // nullable: an empty ?sync_status= means "no filter".
            'sync_status' => ['sometimes', 'nullable', Rule::enum(SyncStatus::class)],
        ];
    }

    /**
     * The status filter, or null when none was given.
     */
    public function syncStatus(): ?SyncStatus
    {
        return $this->enum('sync_status', SyncStatus::class);
    }
}
