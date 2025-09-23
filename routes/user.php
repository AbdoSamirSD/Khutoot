<?php

use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\ReviewController;
use App\Http\Controllers\User\WalletController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\TicketController;
use App\Http\Controllers\User\TripInstanceController;
use Illuminate\Support\Facades\Route;




Route::prefix('user/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);              // Done
    Route::post('/login', [AuthController::class, 'login']);                    // Done 
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']); 
});

Route::prefix('user')
->middleware('auth:sanctum')
->group(function () {
    Route::prefix('profile')
    ->group(function () {
        Route::get('/', [ProfileController::class, 'index']);                           // Done
        Route::put('/', [ProfileController::class, 'updateProfile']);                   // Done
        Route::delete('/', [ProfileController::class, 'deleteProfile']);                // Done
        Route::post('/change-password', [ProfileController::class, 'changePassword']);  // Done
    });
    
    Route::post('/logout', [AuthController::class, 'logout']);                  // Done
        // Route::prefix('settings')
        // ->group(function () {
        // });

        Route::prefix('wallet')->group(function(){
            Route::get('/', [WalletController::class, 'index']); // User wallet overview
            // Done

            Route::post('/add', [WalletController::class, 'addFunds']); // Add funds to wallet (send screenshot of payment, amount and reference number)
            // Done
            
            // Route::post('/withdraw', [WalletController::class, 'withdrawFunds']); // Withdraw funds from wallet
            Route::get('/transactions', [WalletController::class, 'transactionHistory']); // View transaction history
            // Done
        });

        Route::prefix('book-ticket')->group(function(){
            Route::post('/', [TicketController::class, 'bookTickets']); // Book tickets
            // Done
            Route::get('/', [TicketController::class, 'listBooks']); // List all tickets (history of trips booked)
            // Done
            Route::get('/{BookingId}', [TicketController::class, 'viewBooking']); // View book details
            // Done
            Route::delete('/{ticketId}', [TicketController::class, 'cancelTicket']); // Cancel a ticket
            // Done
        });

        Route::prefix('trips')->group(function(){
            Route::get('/', [TripInstanceController::class, 'listTrips']); // List available trips
            // Done

            Route::get('/search', [TripInstanceController::class, 'searchTrips']); // Search trips by criteria
            // Done
            
            Route::get('/{tripInstanceId}', [TripInstanceController::class, 'viewTrip']); // View trip details and available seats
            // Done
            
            // Route::get('/{tripInstanceId}/tracking', [TripInstanceController::class, 'tracking']); // Real-time tracking of trip instance
        });

        Route::prefix('notifications')->group(function(){
            Route::get('/', [NotificationController::class, 'index']); // List notifications
            Route::post('/mark-read', [NotificationController::class, 'markAsRead']); // Mark notifications as read
            Route::delete('/clear', [NotificationController::class, 'clear']); // Clear all notifications
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']); // Get count of unread notifications
        });

        Route::prefix('reviews')->group(function(){
            Route::post('/', [ReviewController::class, 'submitTripReview']); // Submit a review for a trip
            // Done
            Route::get('/{tripId}', [ReviewController::class, 'viewReviews']); // View reviews for a trip
            // Done
            //Route::post('/driver', [ReviewController::class, 'submitDriverReview']);
        });

        Route::prefix('report')->group(function(){
            Route::post('/', [ReportController::class, 'reportIssue']); // Report an issue with a trip
            // Done
            Route::get('/', [ReportController::class, 'viewReports']); // List user's reports
            // Done
            Route::get('/{report_number}', [ReportController::class, 'viewReportDetails']);
            // Done
        });
    });

