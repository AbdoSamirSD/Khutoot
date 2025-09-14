<?php

use app\Http\Controllers\User\AuthController;
use app\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\ReviewController;
use app\Http\Controllers\User\WalletController;
use app\Http\Controllers\User\NotificationController;
use app\Http\Controllers\User\TicketController;
use app\Http\Controllers\User\TripInstanceController;
use Illuminate\Support\Facades\Route;




Route::prefix('user/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
});

Route::prefix('user')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::prefix('profile')
            ->group(function () {
                Route::get('/', [ProfileController::class, 'index']);
                Route::put('/', [ProfileController::class, 'updateProfile']);
                Route::delete('/', [ProfileController::class, 'deleteProfile']);
                Route::post('/change-password', [ProfileController::class, 'changePassword']);
            });

        // Route::prefix('settings')
        // ->group(function () {
        // });

        Route::prefix('wallet')->group(function(){
            Route::get('/', [WalletController::class, 'index']); // User wallet overview
            Route::post('/add', [WalletController::class, 'addFunds']); // Add funds to wallet (send screenshot of payment, amount and reference number)
            // Route::post('/withdraw', [WalletController::class, 'withdrawFunds']); // Withdraw funds from wallet
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
            Route::get('/search', [TripInstanceController::class, 'searchTrips']); // Search trips by criteria
            Route::get('/{tripId}', [TripInstanceController::class, 'viewTrip']); // View trip details and available seats
            Route::get('/{tripInstanceId}/tracking', [TripInstanceController::class, 'tracking']); // Real-time tracking of trip instance
        });

        Route::prefix('notifications')->group(function(){
            Route::get('/', [NotificationController::class, 'index']); // List notifications
            Route::post('/mark-read', [NotificationController::class, 'markAsRead']); // Mark notifications as read
            Route::delete('/clear', [NotificationController::class, 'clear']); // Clear all notifications
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']); // Get count of unread notifications
        });

        Route::prefix('reviews')->group(function(){
            Route::post('/', [ReviewController::class, 'submitReview']); // Submit a review for a trip
            Route::get('/{tripId}', [ReviewController::class, 'viewReviews']); // View reviews for a trip
        });

        Route::prefix('report')->group(function(){
            Route::post('/bus', [ReportController::class, 'reportIssue']); // Report an issue with a trip
            Route::post('/driver', [ReportController::class, 'reportDriver']); // Report an issue with another user
            Route::post('/', [ReportController::class, 'reportIssue']); // Report an issue with a trip
        });
    });

