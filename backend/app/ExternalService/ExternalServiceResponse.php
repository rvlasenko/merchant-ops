<?php

namespace App\ExternalService;

use App\Enums\SyncErrorCode;

/**
 * Outcome of one send to the external service: either success with the
 * external order ID, or failure with an error code.
 */
final readonly class ExternalServiceResponse
{
    private function __construct(
        public ?string $externalOrderId,
        public ?SyncErrorCode $errorCode,
    ) {}

    public static function success(string $externalOrderId): self
    {
        return new self($externalOrderId, null);
    }

    public static function failure(SyncErrorCode $errorCode): self
    {
        return new self(null, $errorCode);
    }

    public function isSuccess(): bool
    {
        return $this->errorCode === null;
    }
}
