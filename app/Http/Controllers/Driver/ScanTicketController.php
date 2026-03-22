<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use Validator;
class ScanTicketController extends Controller
{
    public function scanTicket(Request $request)
    {
        $driver = auth('driver')->user();
        if (!$driver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'ticket_number' => 'required|string|exists:tickets,ticket_number',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = Ticket::with(['booking.tripInstance', 'booking.seat', 'booking.user'])
            ->where('ticket_number', $request->ticket_number)->first();

        $tripInstance = $ticket->booking->tripInstance;
        $trip = $tripInstance->trip;
        // dd($tripInstance);

        if (!$tripInstance) {
            return response()->json(['error' => 'Trip instance not found'], 404);
        }

        if($trip->driver_id !== $driver->id) {
            return response()->json(['error' => 'You are not authorized to scan this ticket'], 403);
        }

        // Perform the ticket scanning logic here

        if ($ticket->status === 'used') {
            return response()->json(['error' => 'Ticket has already been used'], 400);
        }

        if ($ticket->status === 'canceled') {
            return response()->json(['error' => 'Ticket has been canceled'], 400);
        }

        $seat = $ticket->booking->seat;
        if($seat){
            $seat->status = 'used';
            $seat->save();
        }

        $ticket->status = 'used';
        $ticket->save();
        return response()->json([
            'message' => 'Ticket scanned successfully',
            'ticket_number' => $ticket->ticket_number,
            'trip_instance' => $tripInstance->id,
            'seat_number' => $seat ? $seat->seat_number : null,
            'passenger_name' => $ticket->booking->user->name,
            'passenger_phone' => $ticket->booking->user->phone,
        ], 200);
    }
}
