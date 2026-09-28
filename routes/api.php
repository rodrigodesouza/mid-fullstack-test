<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthorizationController;
use App\Http\Controllers\Api\EventController;
use Illuminate\Support\Facades\Route;

Route::prefix('network')->group(function () {
    Route::post('/events', [EventController::class, 'store']);
    Route::post('/authorizations', [AuthorizationController::class, 'store']);
});
