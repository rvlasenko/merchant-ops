<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSimulatedFailureRequest;
use App\Http\Resources\SimulatedFailureResource;
use App\Models\Order;
use App\Models\SimulatedFailure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Operator tool for the simulated external service (task 1.3, §6.5–6.6).
 */
class SimulatedFailureController extends Controller
{
    public function update(UpdateSimulatedFailureRequest $request, Order $order): JsonResponse
    {
        $simulation = SimulatedFailure::updateOrCreate(
            ['order_id' => $order->id],
            $request->validated(),
        );

        // Always 200: a resource of a newly created model would otherwise answer 201.
        return (new SimulatedFailureResource($simulation))->response()->setStatusCode(200);
    }

    public function destroy(Order $order): Response
    {
        // Idempotent: 204 also when there was no simulation (P4).
        SimulatedFailure::where('order_id', $order->id)->delete();

        return response()->noContent();
    }
}
