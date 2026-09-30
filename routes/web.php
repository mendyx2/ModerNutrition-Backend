<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status'    => 'online',
        'service'   => 'ModerNutrition API Backend',
        'version'   => '1.0.0',
        'health'    => '/up',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/up', function () {
    return response()->json(['status' => 'up'], 200);
});
