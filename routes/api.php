<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ClassifyController;
use App\Http\Controllers\Api\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'version' => config('app.version', 'unknown'),
        'time' => now()->toIso8601String(),
    ]);
});

Route::middleware('throttle:60,1')->group(function (): void {
    Route::post('/classify', [ClassifyController::class, 'classify']);
});

Route::middleware('throttle:5,1')->group(function (): void {
    Route::post('/waitlist', [WaitlistController::class, 'store']);
});
