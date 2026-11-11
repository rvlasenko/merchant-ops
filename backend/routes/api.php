<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\SimulatedFailureController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// A non-numeric order ID, or one too long for bigint, is 404 and never reaches
// PostgreSQL, which would reject it with 500.
Route::pattern('order', '[0-9]{1,18}');

Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::post('/orders/{order}/sync', [OrderController::class, 'sync']);
Route::put('/orders/{order}/simulated-failure', [SimulatedFailureController::class, 'update']);
Route::delete('/orders/{order}/simulated-failure', [SimulatedFailureController::class, 'destroy']);

Route::get('/health', function () {
    $timestamp = now()->toIso8601String();

    try {
        DB::select('select 1');
    } catch (Throwable) {
        return response()->json(['status' => 'error', 'database' => 'unavailable', 'timestamp' => $timestamp], 503);
    }

    return response()->json(['status' => 'ok', 'database' => 'ok', 'timestamp' => $timestamp]);
});
