<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ClassifyController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'version' => config('app.version', 'unknown'),
        'time' => now()->toIso8601String(),
    ]);
});

Route::middleware('throttle:classify')->group(function (): void {
    Route::post('/classify', [ClassifyController::class, 'classify']);
});
