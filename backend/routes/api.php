<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $timestamp = now()->toIso8601String();

    try {
        DB::select('select 1');
    } catch (Throwable) {
        return response()->json(['status' => 'error', 'database' => 'unavailable', 'timestamp' => $timestamp], 503);
    }

    return response()->json(['status' => 'ok', 'database' => 'ok', 'timestamp' => $timestamp]);
});
