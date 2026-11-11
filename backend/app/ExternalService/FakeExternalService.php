<?php

namespace App\ExternalService;

use App\Models\Order;
use App\Models\SimulatedFailure;

/**
 * Simulated external service. The only class that reads or changes
 * simulated_failures; it knows nothing about attempts or sync statuses.
 * Fully deterministic: no randomness, no clock, no I/O besides the simulation row.
 */
class FakeExternalService
{
    public function send(Order $order): ExternalServiceResponse
    {
        // Query instead of the relation: a loaded relation may be stale.
        $simulation = SimulatedFailure::where('order_id', $order->id)->first();

        // No simulation, or a limited one that is used up (remaining = 0).
        if ($simulation === null || $simulation->remaining === 0) {
            return ExternalServiceResponse::success('EXT-'.$order->number);
        }

        if ($simulation->remaining !== null) {
            $simulation->decrement('remaining');
        }

        return ExternalServiceResponse::failure($simulation->error_code);
    }
}
