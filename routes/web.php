<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\MyCard;
use Illuminate\Support\Facades\Route;

Route::get('/login', [
    LoginController::class,
    'create',
])->name('login');

Route::post('/login', [
    LoginController::class,
    'store',
]);

Route::post('/logout', [
    LoginController::class,
    'destroy',
])->name('logout');

Route::middleware([
    'auth',
    'role:card_holder',
])->group(function () {

    Route::get('/my-card', MyCard::class)
        ->name('my-card');

});
