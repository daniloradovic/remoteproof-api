<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ClassifyController;
use Illuminate\Support\Facades\Route;

Route::post('/classify', [ClassifyController::class, 'classify']);
