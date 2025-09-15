<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\TripInstance;
use Illuminate\Support\Carbon;
class TripInstanceController extends Controller
{
    // details of current trip instances
    public function index()
    {
        $driver = auth('driver')->user();
        if (!$driver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $today = Carbon::today();
        $tripInstance = TripInstance::with('trip')
            ->whereHas('trip', function($q) use ($driver){
                $q->where('driver_id', $driver->id);
            })
            ->where(function($q) use ($today) {
                $q->whereDate('departure_time', $today)
                ->orWhere('status', 'on_going');
            })
            ->first();

        if(!$tripInstance)
            return response()->json([
                'message' => 'No trip instances found for today',
                'total_trips' => 0,
                'data' => [
                    'trip_instances' => []
                ]
            ]);
        return response()->json([
            'message' => 'Trip instances retrieved successfully',
            'data' => [
                'trip_instance' => [
                    'trip_instance_id' => $tripInstance->id,
                    'trip_id' => $tripInstance->trip_id,
                    'driver_id' => $driver->id,
                    'driver_name' => $driver->name,
                    'status' => $tripInstance->status,
                    'departure_time' => $tripInstance->departure_time->format('Y-m-d H:i:s'),
                    'arrival_time' => $tripInstance->arrival_time->format('Y-m-d H:i:s'),
                    'booked_seats' => $tripInstance->booked_seats,
                    'available_seats' => $tripInstance->available_seats,
                    'stations_of_trip' => $tripInstance->trip->route->routeStations->map(function($routeStation) {
                        return [
                            'station_name' => $routeStation->station->name,
                            'station_order' => $routeStation->station_order,
                        ];
                    })->sortBy('station_order')->values()->all(), // sort by station order and reset keys
                ]
            ]
        ]);
    }

    // filter history of trips
    public function trips($status){
        $driver = auth('driver')->user();
        if (!$driver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if(!in_array($status, ['completed', 'upcoming'])){
            return response()->json(['error' => 'Invalid status. Allowed values are completed or upcoming'], 422);
        }

        $tripInstances = TripInstance::with('trip')
            ->whereHas('trip', function($q) use ($driver){
                $q->where('driver_id', $driver->id);
            })
            ->when($status === 'completed', function ($q) {
                $q->where('departure_time', '<', Carbon::now());
            })
            ->when($status === 'upcoming', function ($q) {
                $q->where('departure_time', '>', Carbon::now());
            })
            ->where('status', $status)
            ->orderBy('departure_time', 'desc')
            ->get();

        if($tripInstances->isEmpty())
            return response()->json([
                'message' => 'No ' . $status . ' trip instances found',
                'total_trips' => 0,
                'data' => [
                    'trip_instances' => [],
                ]
            ]);

        return response()->json([
            'message' => "$status trip instances retrieved successfully",
            'total_trips' => $tripInstances->count(),
            'data' => [
                'trip_instances' => $tripInstances->map(function($tripInstance) use ($driver) {
                    return [
                        'trip_instance_id' => $tripInstance->id,
                        'trip_id' => $tripInstance->trip_id,
                        'driver_id' => $driver->id,
                        'driver_name' => $driver->name,
                        'status' => $tripInstance->status,
                        'departure_time' => $tripInstance->departure_time->format('Y-m-d H:i:s'),
                        'arrival_time' => $tripInstance->arrival_time->format('Y-m-d H:i:s'),
                        'booked_seats' => $tripInstance->booked_seats,
                        'available_seats' => $tripInstance->available_seats,
                    ];
                })
            ]
        ]);
    }

    public function passengers($tripInstanceId){
        // check if the driver who show passengers is valid to do so
        $driver = auth('driver')->user();
        if (!$driver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tripInstance = TripInstance::where('id', $tripInstanceId)
            ->whereHas('trip', function($q) use ($driver) {
                $q->where('driver_id', $driver->id);
            })
            ->first();

        if (!$tripInstance) {
            return response()->json(['error' => 'Trip instance not found or access denied'], 404);
        }

        $passengers = $tripInstance->bookings()->with(['user', 'seat'])->get();

        return response()->json([
            'message' => 'Passengers retrieved successfully',
            'total_passengers' => $passengers->count(),
            'data' => [
                'passengers' => $passengers->map(function($passenger) {
                    return [
                        'booking_id' => $passenger->id,
                        'name' => $passenger->user->name,
                        'seat' => $passenger->seat->seat_number,
                        'phone' => $passenger->user->phone,
                    ];
                })
            ]
        ]);
    }

    public function start($tripInstanceId)
    {
        $driver = auth('driver')->user();
        if (!$driver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // check if the driver has started trips already
        $ongoingTrips = TripInstance::with('trip')
            ->whereHas('trip', function($q) use ($driver){
                $q->where('driver_id', $driver->id);
            })
            ->where('status', 'on_going')->exists();
        if($ongoingTrips){
            return response()->json(['error' => 'You have another ongoing trip. Please end it before starting a new one.'], 400);
        }

        $tripInstance = TripInstance::where('id', $tripInstanceId)
            ->whereHas('trip', function($q) use ($driver) {
                $q->where('driver_id', $driver->id);
            })
            ->with('trip.route.routeStations.station')
            ->first();

        if (!$tripInstance) {
            return response()->json(['error' => 'Trip instance not found or access denied'], 404);
        }

        if($tripInstance->status !== 'upcoming'){
            return response()->json(['error' => 'Trip instance cannot be started. Current status: ' . $tripInstance->status], 400);
        }

        $firstStation = $tripInstance->trip->route->routeStations()->orderBy('station_order')->first();
        if(!$firstStation){
            return response()->json(['error' => 'No stations found for this trip route. Cannot start trip.'], 400);
        }
        
        $tripInstance->status = 'on_going';
        $tripInstance->save();

        $tripInstance->trackings()->create([
            'current_station_id' => $firstStation->station_id,
            'status' => 'arrived',
            'last_updated' => now(),
        ]);


        // notify passengers that the trip has started - to be implemented later
        return response()->json([
            'message' => 'Trip instance started successfully',
            'data' => [
                'trip_instance_id' => $tripInstance->id,
                'status' => $tripInstance->status,
            ]
        ]);

    }

    public function end ($tripInstanceId)
    {
        $driver = auth('driver')->user();
        if (!$driver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tripInstance = TripInstance::where('id', $tripInstanceId)
            ->whereHas('trip', function($q) use ($driver) {
                $q->where('driver_id', $driver->id);
            })
            ->with('trip.route.routeStations.station')
            ->first();

        if (!$tripInstance) {
            return response()->json(['error' => 'Trip instance not found or access denied'], 404);
        }

        if($tripInstance->status !== 'on_going'){
            return response()->json(['error' => 'Trip instance cannot be ended. Current status: ' . $tripInstance->status], 400);
        }
        $lastRouteStation = $tripInstance->trip->route->routeStations()
            ->orderBy('station_order', 'desc')
            ->first();

        $lastReachedStation = $tripInstance->trackings()
        ->where('status', 'arrived')
        ->orderBy('last_updated', 'desc')
        ->first();


        if (!$lastReachedStation || $lastReachedStation->current_station_id !== $lastRouteStation->station_id) {
            return response()->json([
                'error' => 'Trip instance cannot be ended. Last station not reached yet.',
                'current_station' => $lastReachedStation ? $lastReachedStation->currentStation->name : 'Unknown Station',
                'expected_last_station' => $lastRouteStation ? $lastRouteStation->station->name : 'Unknown Station',
            ], 400);
        }
        $tripInstance->status = 'completed';
        $tripInstance->trackings()->create([
            'current_station_id' => $lastRouteStation->station_id,
            'status' => 'departed',
            'last_updated' => now(),
        ]);
        $tripInstance->save();

        return response()->json([
            'message' => 'Trip instance ended successfully',
            'data' => [
                'trip_instance_id' => $tripInstance->id,
                'status' => $tripInstance->status,
            ]
        ]);
    }
}
