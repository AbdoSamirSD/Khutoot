<?php

use app\Http\Controllers\User\AuthController;
use app\Http\Controllers\User\ProfileController;
use app\Http\Controllers\User\WalletController;
use app\Http\Controllers\User\NotificationController;
use app\Http\Controllers\User\TicketController;
use app\Http\Controllers\User\TripInstanceController;
use Illuminate\Support\Facades\Route;
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

        Route::prefix('wallet')->group(function(){
            Route::get('/', [WalletController::class, 'index']); // User wallet overview
            Route::post('/add', [WalletController::class, 'addFunds']); // Add funds to wallet (send screenshot of payment, amount and reference number)
            Route::post('/withdraw', [WalletController::class, 'withdrawFunds']); // Withdraw funds from wallet
            Route::get('/transactions', [WalletController::class, 'transactionHistory']); // View transaction history
        });

        Route::prefix('book-ticket')->group(function(){
            Route::post('/', [TicketController::class, 'bookTicket']); // Book a ticket
            Route::get('/{ticketId}', [TicketController::class, 'viewTicket']); // View ticket details
            Route::get('/', [TicketController::class, 'listTickets']); // List all tickets (history of trips booked)
            Route::delete('/{ticketId}', [TicketController::class, 'cancelTicket']); // Cancel a ticket
        });

        Route::prefix('trips')->group(function(){
            Route::get('/', [TripInstanceController::class, 'listTrips']); // List available trips
            Route::get('/{tripId}', [TripInstanceController::class, 'viewTrip']); // View trip details and available seats
            Route::get('/{tripInstanceId}/tracking', [TripInstanceController::class, 'tracking']); // Real-time tracking of trip instance
        });

        Route::prefix('notifications')->group(function(){
            Route::get('/', [NotificationController::class, 'index']); // List notifications
            Route::post('/mark-read', [NotificationController::class, 'markAsRead']); // Mark notifications as read
            Route::delete('/clear', [NotificationController::class, 'clear']); // Clear all notifications
        });
    });

