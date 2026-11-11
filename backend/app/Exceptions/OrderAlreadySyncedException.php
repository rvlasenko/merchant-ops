<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when a sync is requested for an order that is already synced.
 * No attempt is made and nothing is written. An expected outcome rendered
 * as 409, so it is not logged as an error.
 */
class OrderAlreadySyncedException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('Order is already synced.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
