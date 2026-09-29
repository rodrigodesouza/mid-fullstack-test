<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthorizationController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\StatementController;
use App\Http\Middleware\VerifyNetworkSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('network')
    ->middleware(VerifyNetworkSignature::class)
    ->group(function (): void {
        Route::post('/events', [EventController::class, 'store']);
        Route::post('/authorizations', [AuthorizationController::class, 'store']);
        Route::get('/cards/{card_token}/available', CardController::class);
        Route::get('/cards/{card_token}/statement', StatementController::class);
    });
