<?php

use app\Http\Controllers\User\AuthController;
use app\Http\Controllers\User\ProfileController;
Route::prefix('user/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('user')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::prefix('profile')
            ->group(function () {
                Route::get('/', [ProfileController::class, 'profile']);
                Route::put('/', [ProfileController::class, 'updateProfile']);
                Route::delete('/', [ProfileController::class, 'deleteProfile']);
            });

        Route::prefix('settings')
        ->group(function () {
            Route::get('/', [ProfileController::class, 'settings']);
            Route::put('/', [ProfileController::class, 'updateSettings']);
        });
    });

