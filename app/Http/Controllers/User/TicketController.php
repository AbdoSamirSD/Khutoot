<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Tracking;
use Illuminate\Http\Request;
use App\Models\TripInstance;
use App\Models\Booking;

class TicketController extends Controller
{
    // book tickets
    public function bookTickets(Request $request) {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Validate request
        $validator = \Validator::make($request->all(), [
            'trip_instance_id' => 'required|integer|exists:trip_instances,id',
            'pick_up_station_order' => 'required|integer|min:1',
            'arrival_station_order' => 'required|integer|min:1|gt:pick_up_station_order',
            'seats' => 'required|array|min:1',
            'seats.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Implement booking logic here (omitted for brevity)

        try {
            $response = \DB::transaction(function() use ($user, $request){

                // 1. fetch trip instance
                $tripInstance = TripInstance::with([
                        'trip.bus.seats' => function ($query) {
                            $query->select('id', 'seat_number', 'bus_id')
                            ->orderBy('seat_number');
                        }, 
                        'trip.route.routeStations' => function($query) {
                            $query->with(['station' => function ($query){
                                $query->select('id', 'name');
                            }])->orderBy('station_order');
                        }
                        ])->lockForUpdate()
                        ->find($request->trip_instance_id);
                        if (!$tripInstance) {
                            return response()->json(['error' => 'Trip instance not found'], 404);
                        }
                        
                        if(!in_array($tripInstance->status, ['upcoming', 'on_going'])){
                            return response()->json([
                                'error' => 'Trip is already completed or cancelled. book an upcomin or on_going trip!'
                            ], 422);
                        }

                $routeStations = $tripInstance ->trip->route->routeStations->keyBy('station_order');
                $pickUpStation = $routeStations->get($request->pick_up_station_order) ?? null;
                $arrivalStation = $routeStations->get($request->arrival_station_order) ?? null;
                $seatNumberMap = $tripInstance->trip->bus->seats->pluck('seat_number', 'id')->toArray();

                // 2. validate pick-up and arrival stations
                if(!$pickUpStation || !$arrivalStation){
                    return response()->json(['error' => 'Invalid pick-up or arrival station order'], 422);
                }
                
                if($pickUpStation->station_order >= $arrivalStation->station_order){
                    return response()->json(['error' => 'Arrival station must be after pick-up station'], 422);
                }

                // 3. check if pick_up station is already passed
                $isPassed = Tracking::where('trip_instance_id', $tripInstance->id)
                ->where('current_station_id', $pickUpStation->station_id)
                    ->where('status', 'departed')
                    ->exists();
                
                if($isPassed){
                    return response()->json([
                        'error' => 'The trip has already departed from the selected pick-up station. Please choose a different station or trip.'
                    ], 422);
                }

                // 4. check seat availability
                $bookedSeats = array_values(array_unique($request->seats));
                if (count($bookedSeats) !== count($request->seats)) {
                    return response()->json(['error' => 'Duplicate seats selected'], 422);
                }

                $allSeats = $tripInstance->trip->bus->seats->pluck('id')->toArray();
                $invalidSeats = array_diff($bookedSeats, $allSeats);
                if ($invalidSeats) {
                    return response()->json([
                        'error' => 'Some selected seats do not exist',
                        'invalid_seats' => array_map(fn($id) => "Seat ID $id", $invalidSeats)
                    ], 422);
                }
                $unavailableSeats = Ticket::where('trip_instance_id', $tripInstance->id)
                    ->whereIn('seat_status', ['booked', 'reserved'])
                    ->whereIn('seat_id', $bookedSeats)
                    ->join('bookings', 'tickets.booking_id', '=', 'bookings.id')
                    ->join('seats', 'tickets.seat_id', '=', 'seats.id')
                    ->whereRaw('NOT (bookings.end_station_order < ? OR bookings.start_station_order > ?)', [
                        $request->pick_up_station_order,
                        $request->arrival_station_order
                    ])
                    ->select('tickets.seat_id', 'seats.seat_number')
                    ->get()
                    ->map(function ($ticket) {
                        return [
                            'seat_id' => $ticket->seat_id,
                            'seat_number' => $ticket->seat_number,
                            'reason' => 'Seat already booked for overlapping trip segment'
                        ];
                    })->toArray();
                
                if ($unavailableSeats) {
                    $availableSeats = array_map(function ($seatId) use ($seatNumberMap) {
                        return [
                            'seat_id' => $seatId,
                            'seat_number' => $seatNumberMap[$seatId] ?? null,
                        ];
                    }, array_diff($allSeats, array_column($unavailableSeats, 'seat_id')));

                    return response()->json([
                        'error' => 'One or more selected seats are not available',
                        'unavailable_seats' => $unavailableSeats,
                        'available_seats' => $availableSeats
                    ], 422);
                }
                
                // 5. calculate total fare
                $totalFare = count($bookedSeats) * $tripInstance->trip->price;
                $wallet = $user->wallet()->lockForUpdate()->first();
                if($wallet->balance < $totalFare){
                    return response()->json(['error' => 'Insufficient wallet balance'], 422);
                }

                // 6. create ticket records
                // book tickets
                $booking = Booking::create([
                    'user_id' => $user->id,
                    'trip_instance_id' => $tripInstance->id,
                    'route_id' => $tripInstance->trip->route_id,
                    'start_station_order' => $request->pick_up_station_order,
                    'end_station_order' => $request->arrival_station_order,
                    'price' => $totalFare,
                    'status' => 'confirmed',
                ]);
        
                // 7. process payment
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
        
                // 8. return response
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
    
    // list all books (history of trips booked)
    public function listBooks() {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $bookings = Booking::where('user_id', $user->id)
            ->with(['tripInstance.trip'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return response()->json([
            'message' => 'bookings retrieved successfully',
            'data' => $bookings->getCollection()->map(function($booking){
                return [
                    'booking_id' => $booking->id,
                    'trip_name' => $booking->tripInstance->trip->name,
                    'pick_up_station' => $booking->startStation->name,
                    'arrival_station' => $booking->endStation->name
                ];
            }),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ]
        ]);
    }

    // view booking details
    public function viewBooking($BookingId) {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $booking = Booking::where('id', $BookingId)
            ->where('user_id', $user->id)
            ->with(['tripInstance.trip.bus', 'tripInstance.trip.route.routeStations.station', 'tickets.seat'])
            ->first();
        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        return response()->json([
            'message' => 'booking details retrieved succefully',
            'data' => [

                'booking_id' => $booking->id,
                'status' => $booking->status,
                'total_fare' => $booking->price,
                'pick_up_station' => $booking->startStation?->name,
                'arrival_station' => $booking->endStation?->name,
                'trip_instance' => [
                    'id' => $booking->tripInstance->id,
                    'departure_time' => $booking->tripInstance->departure_time,
                    'arrival_time' => $booking->tripInstance->arrival_time,
                    
                    'trip' => [
                        'id' => $booking->tripInstance->trip->id ?? null,
                        'name' => $booking->tripInstance->trip->name ?? null,
                        'bus' => [
                            'id' => $booking->tripInstance->trip->bus->id ?? null,
                            'license_plate' => $booking->tripInstance->trip->bus->license_plate ?? null,
                        ],
                        'route' => [
                            'id' => $booking->tripInstance->trip->route->id ?? null,
                            'name' => $booking->tripInstance->trip->route->name ?? null,
                            'stations' => $booking->tripInstance->trip->route->routeStations->map(function($rs) {
                                return [
                                    'station_id' => $rs->station->id ?? null,
                                    'station_name' => $rs->station->name ?? null,
                                    'station_order' => $rs->station_order ?? null,
                                ];
                            })->sortBy('station_order')->values()->all(),
                        ],
                    ],
                ],
                'tickets' => $booking->tickets->map(function($ticket) {
                    return [
                        'ticket_id' => $ticket->id ?? null,
                        'ticket_number' => $ticket->ticket_number ?? null,
                        'seat_number' => $ticket->seat?->seat_number ,
                        'status' => $ticket->status ?? null,
                    ];
                })->all(),
            ],
        ], 200);
    }

    // cancel a ticket
    public function cancelTicket($ticketId) {
        // Business logic to cancel the ticket by $ticketId
        // Check if the ticket belongs to the authenticated user
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try{
            $response = \DB::transaction(function() use ($user,$ticketId){
                
                $ticket = Ticket::lockForUpdate()->find($ticketId);
                if (!$ticket) {
                    return response()->json(['error' => 'Ticket not found'], 404);
                }

                if(in_array($ticket->status , ['used', 'canceled'])){
                    return response()->json(['message' => 'Ticket is already canceled or used'], 200);
                }

                $booking = Booking::where('id', $ticket->booking_id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (!$booking) {
                    return response()->json(['error' => 'Ticket does not belong to the user'], 403);
                }
                // Check cancellation policy (e.g., time limits, fees)
                $tripInstance = TripInstance::find($booking->trip_instance_id);
                if (!$tripInstance) {
                    return response()->json(['error' => 'Associated trip instance not found'], 404);
                }

                // check if the start station is passed
                $isPassed = Tracking::where('trip_instance_id', $tripInstance->id)
                    ->where('current_station_id', $booking->start_station_id)
                    ->whereIn('status', ['arrived', 'departed'])
                    ->limit(1)
                    ->exists();
                if($isPassed){
                    return response()->json([
                        'message' => 'Cannot cancel booking. Station aleardy passed'
                    ], 422);
                }
                
                // Update ticket status to cancelled
                $ticket->status = 'canceled';
                $ticket->seat_status = 'available';
                $ticket->save();

                $activeTicketsExist = Ticket::where('booking_id', $booking->id)
                    ->where('status', '!=', 'canceled')
                    ->exists();
                if (!$activeTicketsExist) {
                    $booking->status = 'canceled';
                }
                $booking->save();

                // Process refund if applicable
                $wallet = $user->wallet()->lockForUpdate()->first();
                $wallet->balance += $tripInstance->trip->price;
                $wallet->save();
                $wallet->transactions()->create([
                    'user_wallet_id' => $wallet->id,
                    'amount' => $tripInstance->trip->price,
                    'type' => 'credit',
                    'booking_id' => $booking->id
                ]);

                return response()->json([
                    'message' => 'Ticket cancelled successfully',
                    'refund' => $tripInstance->trip->price,
                    'wallet_balance' => $wallet->balance,
                ], 200);
            }, 5);

            return $response;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Cancellation failed: '.$e->getMessage()], 500);
        }
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
    
    
    
    
    
    
    
    // public function bookTickets(Request $request) {

    //     $user = auth()->user();
    //     if (!$user) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     // Validate request
    //     $validator = \Validator::make($request->all(), [
    //         'trip_instance_id' => 'required|integer|exists:trip_instances,id',
    //         'pick_up_station_id' => 'required|integer|exists:stations,id',
    //         'arrival_station_id' => 'required|integer|exists:stations,id|different:pick_up_station_id',
    //         'seats' => 'required|array|min:1',
    //         'seats.*' => 'integer|min:1',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }


    //     try {
    //         $response = \DB::transaction(function () use ($user, $request) 
    //         {
    //             // 1. Fetch trip instance with related trip and bus details
    //             $tripInstance = TripInstance::with([
    //                     'trip.bus.seats' => function ($query) {
    //                         $query->select('id', 'seat_number', 'bus_id')
    //                             ->orderBy('seat_number');
    //                     }, 
    //                     'trip.route.routeStations' => function($query) {
    //                         $query->with(['station' => function ($query){
    //                             $query->select('id', 'name');
    //                         }])->orderBy('station_order');
    //                     }
    //                 ])->lockForUpdate()
    //                 ->find($request->trip_instance_id);
    //             if (!$tripInstance) {
    //                 return response()->json(['error' => 'Trip instance not found'], 404);
    //             }

    //             if(!in_array($tripInstance->status, ['upcoming', 'on_going'])){
    //                 return response()->json([
    //                     'error' => 'Trip is already completed or cancelled. book an upcomin or on_going trip!'
    //                 ], 422);
    //             }

    //             $isPassed = Tracking::where('trip_instance_id', $tripInstance->id)
    //                 ->where('current_station_id', $request->pick_up_station_id)
    //                 ->where('status', 'departed')
    //                 ->exists();
                
    //             if($isPassed){
    //                 return response()->json([
    //                     'error' => 'The trip has already departed from the selected pick-up station. Please choose a different station or trip.'
    //                 ], 422);
    //             }
    //             // 2. Validate pick-up and arrival stations
    //             $stations = $tripInstance->trip->route->routeStations->sortBy('station_order')->pluck('station_id')->toArray();
    //             $stationIndexMap = array_flip($stations);
    //             $pickUpIndex = $stationIndexMap[$request->pick_up_station_id] ?? false;
    //             $arrivalIndex = $stationIndexMap[$request->arrival_station_id] ?? false;
    //             if ($pickUpIndex === false || $arrivalIndex === false || $arrivalIndex <= $pickUpIndex) {
    //                 return response()->json(['error' => 'Invalid pick-up or arrival station'], 422);
    //             }


    //             // 3. Check seat availability
    //             $bookedSeats = array_values(array_unique($request->seats));
    //             if (count($bookedSeats) !== count($request->seats)) {
    //                 return response()->json(['error' => 'Duplicate seats selected'], 422);
    //             }

    //             $allSeats = $tripInstance->trip->bus->seats->pluck('id')->toArray();
    //             $seatNumberMap = $tripInstance->trip->bus->seats->pluck('seat_number', 'id')->toArray();
    //             $invalidSeats = array_diff($bookedSeats, $allSeats);
    //             if ($invalidSeats) {
    //                 return response()->json([
    //                     'error' => 'Some selected seats do not exist',
    //                     'invalid_seats' => array_map(fn($id) => "Seat ID $id", $invalidSeats)
    //                 ], 422);
    //             }

    //             $allTickets = Ticket::where('trip_instance_id', $tripInstance->id)
    //                 ->whereIn('seat_status', ['booked', 'reserved'])
    //                 ->whereIn('seat_id', $bookedSeats)
    //                 ->with('booking')
    //                 ->lockForUpdate()
    //                 ->get();
                
    //             $unavailableSeats = [];
    //             $availableSeatMap = array_flip($bookedSeats);

    //             foreach($allTickets as $ticket){
    //                 if(!isset($availableSeatMap[$ticket->seat_id])) continue;

    //                 $ticketPickUpIndex = $stationIndexMap[$ticket->booking->start_station_id] ?? null;
    //                 $ticketArrivalIndex = $stationIndexMap[$ticket->booking->end_station_id] ?? null;
    //                 if($ticketPickUpIndex === null || $ticketArrivalIndex === null) continue;


    //                 $hasOverlap = !($arrivalIndex <= $ticketPickUpIndex || $pickUpIndex >= $ticketArrivalIndex);
    //                 if($hasOverlap){
    //                     $unavailableSeats[] = [
    //                         'seat_id' => $ticket->seat_id,
    //                         'seat_number' => $seatNumberMap[$ticket->seat_id] ?? null,
    //                         'reason' => 'Seat already booked for overlapping trip segment'
    //                     ];
    //                     unset($availableSeatMap[$ticket->seat_id]);
    //                 }
    //             }

    //             if($unavailableSeats){
    //                 $availableSeats = array_map(function ($seatId) use ($seatNumberMap) {
    //                     return [
    //                         'seat_id' => $seatId,
    //                         'seat_number' => $seatNumberMap[$seatId] ?? null,
    //                     ];
    //                 }, array_keys($availableSeatMap));

    //                 return response()->json([
    //                     'error' => 'One or more selected seats are not available', 
    //                     'unavailable_seats' => $unavailableSeats,
    //                     'available_seats' => $availableSeats
    //                 ], 422);
    //             }
    //             // $availableSeats = $tripInstance->trip->bus->seats->whereIn('id', $bookedSeats);
                
    //             // 4. Calculate total fare
    //             $totalFare = count($bookedSeats) * $tripInstance->trip->price;
    //             $wallet = $user->wallet()->lockForUpdate()->first();
    //             if($wallet->balance < $totalFare){
    //                 return response()->json(['error' => 'Insufficient wallet balance'], 422);
    //             }

    //             // 3. Create ticket records
    //             // book tickets
    //             $booking = Booking::create([
    //                 'user_id' => $user->id,
    //                 'trip_instance_id' => $tripInstance->id,
    //                 'start_station_id' => $request->pick_up_station_id,
    //                 'end_station_id' => $request->arrival_station_id,
    //                 'price' => $totalFare,
    //                 'status' => 'confirmed',
    //             ]);
        
        
    //             // 4. Process payment
    //             // create tickets for each seat
    //             $ticketsData = [];
    //             foreach($bookedSeats as $seatId){
    //                 $ticketsData[] = [
    //                     'booking_id' => $booking->id,
    //                     'ticket_number' => 'TCKT-' . strtoupper(\Str::uuid()),
    //                     'status' => 'valid',
    //                     'seat_id' => $seatId,
    //                     'seat_status' => 'booked',
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ];
    //             }
                
    //             Ticket::insert($ticketsData);
    //             $tickets = Ticket::where('booking_id', $booking->id)->with('seat')->get();
                
                
    //             // Deduct from wallet
    //             $wallet->balance -= $totalFare;
    //             $wallet->save();
                
    //             // record wallet transaction
    //             $wallet->transactions()->create([
    //                 'user_wallet_id' => $wallet->id,
    //                 'amount' => $totalFare,
    //                 'type' => 'debit',
    //                 'booking_id' => $booking->id,
    //             ]);
        
        
    //             // 5. Return response
    //             return response()->json([
    //                 'message' => 'Tickets booked successfully',
    //                 'tickets' => $tickets->map(function($ticket) {
    //                     return [
    //                         'ticket_id' => $ticket->id,
    //                         'ticket_number' => $ticket->ticket_number,
    //                         'seat_number' => $ticket->seat->seat_number,
    //                         'status' => $ticket->status,
    //                     ];
    //                 })->all(),
    //                 'booking_id' => $booking->id,
    //                 'booking_status' => $booking->status,
    //                 'total_fare' => $totalFare,
    //                 'wallet_balance' => $wallet->balance,
    //             ], 201);
    //         }, 5); // retry 5 times on deadlock

    //     return $response;

    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Booking failed: ' . $e->getMessage()], 500);
    //     }
    // }
    // }