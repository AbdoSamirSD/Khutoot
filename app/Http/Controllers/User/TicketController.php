<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TripInstance;
use App\Models\Booking;

class TicketController extends Controller
{
    // book tickets
    public function bookTickets(Request $request) {

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Validate request
        $validator = \Validator::make($request->all(), [
            'trip_instance_id' => 'required|integer|exists:trip_instances,id',
            'pick_up_station_id' => 'required|integer|exists:stations,id',
            'arrival_station_id' => 'required|integer|exists:stations,id|different:pick_up_station_id',
            'seats' => 'required|array|min:1',
            'seats.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        // 1. Fetch trip instance with related trip and bus details
        $tripInstance = TripInstance::with(['trip.bus.seats'])->find($request->trip_instance_id);
        if (!$tripInstance) {
            return response()->json(['error' => 'Trip instance not found'], 404);
        }

        // 2. Validate pick-up and arrival stations
        $stations = $tripInstance->trip->route->routeStations->sortBy('station_order')->pluck('station_id')->toArray();
        $pickUpIndex = array_search($request->pick_up_station_id, $stations);
        $arrivalIndex = array_search($request->arrival_station_id, $stations);
        if ($pickUpIndex === false || $arrivalIndex === false || $arrivalIndex <= $pickUpIndex) {
            return response()->json(['error' => 'Invalid pick-up or arrival station'], 422);
        }


        // 3. Check seat availability
        $bookedSeats = array_unique($request->seats);
        if(count($bookedSeats) !== count($request->seats)){
            return response()->json(['error' => 'Duplicate seats selected'], 422);
        }
        
        $availableSeats = $tripInstance->trip->bus->seats->whereIn('id', $bookedSeats);
        if(count($availableSeats) !== count($bookedSeats)){
            return response()->json(['error' => 'One or more selected seats do not exist'], 422);
        }
        foreach($availableSeats as $seat){
            if($seat->status == 'used'){
                return response()->json(['error' => 'Seat '. $seat->seat_number . ' is aleardy booked'], 422);
            }
        }
        
        // 4. Calculate total fare
        $totalFare = count($bookedSeats) * $tripInstance->trip->price;
        $wallet = $user->wallet;
        if($wallet->balance < $totalFare){
            return response()->json(['error' => 'Insufficient wallet balance'], 422);
        }



        // use DB::transaction to ensure atomicity
        try {
            $response = \DB::transaction(function () use ($user, $request, $tripInstance, $availableSeats, $totalFare, $wallet) 
            {

                // book the seats (mark as used)
                foreach($availableSeats as $seat){
                    $seat->update([
                        'status' => 'used'
                    ]);
                }
        
                // 3. Create ticket records
                // book tickets
                $booking = Booking::create([
                    'user_id' => $user->id,
                    'trip_instance_id' => $tripInstance->id,
                    'start_station_id' => $request->pick_up_station_id,
                    'end_station_id' => $request->arrival_station_id,
                    'price' => $totalFare,
                    'status' => 'confirmed',
                ]);
        
        
                // 4. Process payment
                // create tickets for each seat
                $tickets = collect();
                foreach($availableSeats as $seat){
                    $tickets->push($booking->tickets()->create([
                        'booking_id' => $booking->id,
                        'ticket_number' => 'TCKT-' . strtoupper(\Str::random(10)),
                        'status' => 'valid',
                        'seat_id' => $seat->id,
                    ]));
                }
        
                // Deduct from wallet
                $user->wallet->update([
                    'balance' => $wallet->balance - $totalFare
                ]);
                $wallet->refresh();
                
                // record wallet transaction
                $wallet->transactions()->create([
                    'user_wallet_id' => $wallet->id,
                    'amount' => $totalFare,
                    'type' => 'debit',
                    'booking_id' => $booking->id,
                ]);
        
        
                // 5. Return response
                return response()->json([
                    'message' => 'Tickets booked successfully',
                    'tickets' => $tickets->map(function($ticket) {
                        return [
                            'ticket_id' => $ticket->id,
                            'ticket_number' => $ticket->ticket_number,
                            'seat_number' => $ticket->seat->seat_number,
                            'status' => $ticket->status,
                        ];
                    })->all(),
                    'booking_id' => $booking->id,
                    'total_fare' => $totalFare,
                    'wallet_balance' => $wallet->balance,
                ], 201);
            });

            return $response;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Booking failed: ' . $e->getMessage()], 500);
        }
    }

    // view booking details
    public function viewTicket($ticketId) {
        // Business logic to retrieve ticket details by $ticketId
        // Check if the ticket belongs to the authenticated user

        return response()->json([
            'ticket_id' => $ticketId,
            // 'trip_details' => $tripDetails,
            // 'seats' => $seats,
            // 'payment_info' => $paymentInfo,
        ], 200);
    }

    // list all tickets (history of trips booked)
    public function listTickets(Request $request) {
        // Business logic to retrieve all tickets for the authenticated user
        // Implement pagination if necessary

        return response()->json([
            'tickets' => [
                // List of tickets with details
            ],
        ], 200);
    }

    // cancel a ticket
    public function cancelTicket($ticketId) {
        // Business logic to cancel the ticket by $ticketId
        // Check if the ticket belongs to the authenticated user
        // Check cancellation policy (e.g., time limits, fees)
        // Update ticket status to cancelled
        // Process refund if applicable

        return response()->json([
            'message' => 'Ticket cancelled successfully',
        ], 200);
    }
}

        // // Check if selected seats are available
        // foreach ($bookedSeats as $seatId) {
        //     $seatFound = false;
        //     foreach ($availableSeats as $seat) {
        //         if ($seat->id == $seatId && !$seat->is_booked) {
        //             $seatFound = true;
        //             break;
        //         }
        //     }
        //     if (!$seatFound) {
        //         return response()->json(['error' => "Seat ID $seatId is not available"], 422);
        //     }
        // }