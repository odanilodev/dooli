<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'environment' => app()->environment(),
        'timezone' => config('app.timezone'),
        'timestamp' => now()->toIso8601String(),
    ]);
});
