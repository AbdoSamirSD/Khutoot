<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    //
    // Submit a review for a trip
    public function submitTripReview(Request $request)
    {   

        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = \Validator::make($request->all(), [
            'trip_instance_id' => 'required|exists:trip_instances,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if the user has already reviewed this trip instance
        $existingReview = \App\Models\Review::where('user_id', $user->id)
            ->where('trip_instance_id', $request->trip_instance_id)
            ->first();
        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this trip.'], 400);
        }

        // check if the user has booked this trip instance
        $hasBooked = Booking::where('user_id', $user->id)
            ->where('trip_instance_id', $request->trip_instance_id)->exists();
        
        if(!$hasBooked){
            return response()->json(['message' => 'You can only review trips you have booked.'], 400);
        }

        // Create the review
        $tripInstance = \App\Models\TripInstance::with('trip')->find($request->trip_instance_id);
        $review = \App\Models\Review::create([
            'user_id' => $user->id,
            'trip_instance_id' => $request->trip_instance_id,
            'trip_id' => $tripInstance->trip_id,
            'driver_id' => $tripInstance->trip->driver_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json(['message' => 'Review submitted successfully', 'review' => $review], 201);
    }

    // retrieve reviews for specific trip

    public function viewReviews($tripId){
        $user = auth('user')->user();
        if(!$user){
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $trip = Trip::with(['reviews' => fn($q) => $q->latest()])->find($tripId);
        if(!$trip){
            return response()->json([
                'message' => 'Trip Not Found'
            ], 404);
        }

        $reviews = $trip->reviews()->with('user')->latest()->paginate(10);
        $avgRating = round($trip->reviews()->avg('rating'), 1);
        $countReviews = $trip->reviews()->count();
        return response()->json([
            'message' => 'Reviews retrieved successfully',
            'average_rating' => $avgRating,
            'count' =>$countReviews,
            'data' => $reviews->map(function ($review) use ($trip){
                return[
                    'user' => $review->user->name,
                    'user_image' => $review->user->image ? asset( 'storage/' . $review->user->image) : null,
                    'rating' => $review->rating,
                    'comment' => $review->comment
                ];
            }),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'next_page_url' => $reviews->nextPageUrl(),
                'previous_page_url' => $reviews->previousPageUrl(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ]
        ]);
    }
}
