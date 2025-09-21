<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
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


        try {
            $response = \DB::transaction(function () use ($user, $request) 
            {
                // 1. Fetch trip instance with related trip and bus details
                $tripInstance = TripInstance::with(['trip.bus.seats', 'trip.route.routeStations'])
                    ->lockForUpdate()
                    ->find($request->trip_instance_id);
                if (!$tripInstance) {
                    return response()->json(['error' => 'Trip instance not found'], 404);
                }

                // 2. Validate pick-up and arrival stations
                $stations = $tripInstance->trip->route->routeStations->sortBy('station_order')->pluck('station_id')->toArray();
                $stationIndexMap = array_flip($stations);
                $pickUpIndex = $stationIndexMap[$request->pick_up_station_id] ?? false;
                $arrivalIndex = $stationIndexMap[$request->arrival_station_id] ?? false;
                if ($pickUpIndex === false || $arrivalIndex === false || $arrivalIndex <= $pickUpIndex) {
                    return response()->json(['error' => 'Invalid pick-up or arrival station'], 422);
                }


                // 3. Check seat availability
                $bookedSeats = array_values(array_unique($request->seats));
                if (count($bookedSeats) !== count($request->seats)) {
                    return response()->json(['error' => 'Duplicate seats selected'], 422);
                }

                $allSeats = $tripInstance->trip->bus->seats->pluck('id')->toArray();
                $seatNumberMap = $tripInstance->trip->bus->seats->pluck('seat_number', 'id')->toArray();
                $invalidSeats = array_diff($bookedSeats, $allSeats);
                if ($invalidSeats) {
                    return response()->json([
                        'error' => 'Some selected seats do not exist',
                        'invalid_seats' => array_map(fn($id) => "Seat ID $id", $invalidSeats)
                    ], 422);
                }

                $allTickets = Ticket::where('trip_instance_id', $tripInstance->id)
                    ->whereIn('seat_status', ['booked', 'reserved'])
                    ->whereIn('seat_id', $bookedSeats)
                    ->with('booking')
                    ->lockForUpdate()
                    ->get();
                
                $unavailableSeats = [];
                $availableSeatMap = array_flip($bookedSeats);

                foreach($allTickets as $ticket){
                    if(!isset($availableSeatMap[$ticket->seat_id])) continue;

                    $ticketPickUpIndex = $stationIndexMap[$ticket->booking->start_station_id] ?? null;
                    $ticketArrivalIndex = $stationIndexMap[$ticket->booking->end_station_id] ?? null;
                    if($ticketPickUpIndex === null || $ticketArrivalIndex === null) continue;


                    $hasOverlap = !($arrivalIndex <= $ticketPickUpIndex || $pickUpIndex >= $ticketArrivalIndex);
                    if($hasOverlap){
                        $unavailableSeats[] = [
                            'seat_id' => $ticket->seat_id,
                            'seat_number' => $seatNumberMap[$ticket->seat_id] ?? null,
                            'reason' => 'Seat already booked for overlapping trip segment'
                        ];
                        unset($availableSeatMap[$ticket->seat_id]);
                    }
                }

                if($unavailableSeats){
                    $availableSeats = array_map(function ($seatId) use ($seatNumberMap) {
                        return [
                            'seat_id' => $seatId,
                            'seat_number' => $seatNumberMap[$seatId] ?? null,
                        ];
                    }, array_keys($availableSeatMap));

                    return response()->json([
                        'error' => 'One or more selected seats are not available', 
                        'unavailable_seats' => $unavailableSeats,
                        'available_seats' => $availableSeats
                    ], 422);
                }
                // $availableSeats = $tripInstance->trip->bus->seats->whereIn('id', $bookedSeats);
                
                // 4. Calculate total fare
                $totalFare = count($bookedSeats) * $tripInstance->trip->price;
                $wallet = $user->wallet()->lockForUpdate()->first();
                if($wallet->balance < $totalFare){
                    return response()->json(['error' => 'Insufficient wallet balance'], 422);
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
                $ticketsData = [];
                foreach($bookedSeats as $seatId){
                    $ticketsData[] = [
                        'booking_id' => $booking->id,
                        'ticket_number' => 'TCKT-' . strtoupper(\Str::uuid()),
                        'status' => 'valid',
                        'seat_id' => $seatId,
                        'seat_status' => 'booked',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                
                Ticket::insert($ticketsData);
                $tickets = Ticket::where('booking_id', $booking->id)->with('seat')->get();
                
                
                // Deduct from wallet
                $wallet->balance -= $totalFare;
                $wallet->save();
                
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
                    'booking_status' => $booking->status,
                    'total_fare' => $totalFare,
                    'wallet_balance' => $wallet->balance,
                ], 201);
            }, 5); // retry 5 times on deadlock

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