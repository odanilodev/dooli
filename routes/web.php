<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'type' => 'api',
        'status' => 'ok',
        'health' => url('/api/health'),
    ]);
});
