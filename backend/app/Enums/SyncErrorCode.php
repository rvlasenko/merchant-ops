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
            self::Timeout => 'External service did not respond in time.',
            self::Unavailable => 'External service is temporarily unavailable.',
            self::RateLimited => 'Too many requests to the external service.',
            self::ValidationFailed => 'External service rejected the order data.',
            self::Unauthorized => 'External service credentials were rejected.',
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
