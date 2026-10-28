<?php

namespace App\Enums;

enum SyncErrorCode: string
{
    case Timeout = 'timeout';
    case Unavailable = 'unavailable';
    case RateLimited = 'rate_limited';
    case ValidationFailed = 'validation_failed';
    case Unauthorized = 'unauthorized';

    public function message(): string
    {
        return match ($this) {
            self::Timeout => 'Marketplace did not respond in time.',
            self::Unavailable => 'Marketplace is temporarily unavailable.',
            self::RateLimited => 'Too many requests to the marketplace.',
            self::ValidationFailed => 'Marketplace rejected the order data.',
            self::Unauthorized => 'Marketplace credentials were rejected.',
        };
    }

    public function retryable(): bool
    {
        return match ($this) {
            self::Timeout, self::Unavailable, self::RateLimited => true,
            self::ValidationFailed, self::Unauthorized => false,
        };
    }
}
