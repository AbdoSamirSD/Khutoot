<?php

use App\Http\Controllers\Driver\AuthController;
use App\Http\Controllers\Driver\TripInstanceController ;
use App\Http\Controllers\Driver\ScanTicketController;
// use App\Http\Controllers\Driver\WalletController;
use App\Http\Controllers\Driver\TrackingController;


Route::prefix('driver/auth')->group(callback: function () {
    Route::post('/login', [AuthController::class, 'login']);        // Done
    Route::post('/logout', [AuthController::class, 'logout']);      // Done
});

Route::prefix('driver')
    ->middleware('auth:driver')
    ->group(function () {
        Route::prefix('trip')
            ->group(function () {
                Route::get('/', [TripInstanceController::class, 'index']);          // show ongoing trip (current trip)
                Route::get('/{status}', [TripInstanceController::class, 'trips']);  // show completed or upcoming trips
                //passengers
                Route::get('/{tripInstanceId}/passengers', [TripInstanceController::class, 'passengers']);
                Route::get('/{tripInstanceId}/start', [TripInstanceController::class, 'start']);
                Route::get('/{tripInstanceId}/end', [TripInstanceController::class, 'end']);
            });

        Route::prefix('scan-ticket')
        ->group(function () {
            Route::post('/', [ScanTicketController::class, 'scanTicket']); // Done
        });

        Route::prefix('tracking')
        ->group(function (): void {
            Route::post('/{tripInstanceId}', [TrackingController::class, 'updateStatus']);
            // Route::post('/{tripInstanceId}', [TrackingController::class, 'show']);
            // Route::post('/{tripInstanceId}/depart', [TrackingController::class, 'depart']);
            // Route::post('/{tripInstanceId}/delayed', [TrackingController::class, 'delayed']);
        });

        // Route::prefix('wallet')
        // ->group(function () {
        //     Route::get('/', [WalletController::class, 'index']);
        //     Route::post('/withdraw', [WalletController::class, 'withdraw']);
        //     Route::get('/transactions', [WalletController::class, 'transactions']);
        // });
    });

