<?php

use App\Http\Controllers\PublicFileController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

// The SPA is served separately; the backend root only reports that it is alive.
Route::get('/', StatusController::class);

// Fallback for uploaded files when `php artisan storage:link` has not been run.
// (Controller-based on purpose: closure routes break `php artisan route:cache`.)
Route::get('/storage/{path}', PublicFileController::class)->where('path', '.*');
