<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');
Route::view('/privacy', 'privacy')->name('privacy');
