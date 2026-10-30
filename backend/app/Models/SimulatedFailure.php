<?php

namespace App\Models;

use App\Enums\SyncErrorCode;
use Database\Factories\SimulatedFailureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulatedFailure extends Model
{
    /** @use HasFactory<SimulatedFailureFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'error_code',
        'remaining',
    ];

    protected function casts(): array
    {
        return [
            'error_code' => SyncErrorCode::class,
            'remaining' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
