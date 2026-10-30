<?php

namespace App\Models;

use App\Enums\AttemptResult;
use App\Enums\AttemptTrigger;
use App\Enums\SyncErrorCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncAttempt extends Model
{
    // Attempts are an append-only log: there is no updated_at column.
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'trigger',
        'result',
        'error_code',
        'error_message',
        'retryable',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => AttemptTrigger::class,
            'result' => AttemptResult::class,
            'error_code' => SyncErrorCode::class,
            'retryable' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
