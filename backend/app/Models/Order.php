<?php

namespace App\Models;

use App\Enums\SyncErrorCode;
use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'number',
        'customer_name',
        'total_cents',
        'currency',
        'sync_status',
        'marketplace_order_id',
        'last_error_code',
        'attempts_count',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'sync_status' => SyncStatus::class,
            'last_error_code' => SyncErrorCode::class,
            'total_cents' => 'integer',
            'attempts_count' => 'integer',
            'last_attempt_at' => 'immutable_datetime',
        ];
    }

    public function syncAttempts(): HasMany
    {
        return $this->hasMany(SyncAttempt::class);
    }

    public function simulatedFailure(): HasOne
    {
        return $this->hasOne(SimulatedFailure::class);
    }
}
