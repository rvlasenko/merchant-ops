<?php

namespace App\Http\Requests;

use App\Enums\SyncErrorCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSimulatedFailureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * remaining must be sent explicitly: null means "fail forever" and must not
     * happen by omission (P7). 0 is not accepted: a simulation is removed with
     * DELETE (D1). Strict integer within int4, so booleans, strings and
     * values the column cannot store are 422, not a database error.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'error_code' => ['required', Rule::enum(SyncErrorCode::class)],
            'remaining' => ['present', 'nullable', 'integer:strict', 'min:1', 'max:2147483647'],
        ];
    }
}
