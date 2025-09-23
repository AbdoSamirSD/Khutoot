<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\TripInstance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TripInstanceController extends Controller
{
    //
    public function listTrips(){
        $user = auth('user')->user();
        if(!$user){
            return response()->json([
                'message' => 'Unauthenticated'
            ]);
        }

        $tripInstances = TripInstance::where('departure_time', '>=', Carbon::now())
            ->with('trip')
            ->whereHas('trip', function($q) use ($user){
                $q->where('location', 'like', "%{$user->city}%");
            })
            ->orderBy('departure_time', 'asc')
            ->take(5)
            ->get();
        if($tripInstances->isEmpty()){
            // retrieve newest 5 trips of today
            $tripInstances = TripInstance::with('trip')
                ->orderBy('departure_time', 'asc')
                ->where('departure_time',  '>=', Carbon::now())
                ->take(5)
                ->get();
        }

        return response()->json([
            'message' => 'trips retrieved successfully.',
            'data' => $tripInstances->map(function($tripInstance){
                return [
                    'trip_instance_id' => $tripInstance->id,
                    'trip_location' => $tripInstance->trip->location ?? null,
                    'arrival_time' => $tripInstance->arrival_time,
                    'departure_time' => $tripInstance->departure_time,
                    'status' => $tripInstance->status
                ];
            }),
        ], 200);
    }

    public function searchTrips($query){
        $user = auth('user')->user();
        if(!$user){
            return response()->json([
                'message' => 'Unauthenticated'
            ]);
        }

        $tripInstances = TripInstance::with(
            ['trip' => fn($q) => $q->select('id', 'location'),
                        'trip.route' => fn($q) => $q->select('id', 'name', 'source', 'destination'),
                        'trip.route.routeStations.station' => fn($q) => $q->select('id', 'name', 'city')]
            )
            ->whereHas('trip', function($q) use ($query, $user){
                $q->where('location', 'like', "%{$query}%")
                  ->orWhereHas('route', function($q2) use ($query, $user){
                        $q2->where('name', 'like', "%{$query}%")
                           ->orWhere('source', 'like', "%{$query}%")
                           ->orWhere('destination', 'like', "%{$query}%")
                           ->orWhereHas('routeStations.station', function($q3) use ($query, $user){
                                $q3->where('name', 'like', "%{$query}%")
                                ->orWhere('city', 'like', "%{$query}%");
                        });
                  });
            })
            ->where('departure_time', '>=', Carbon::now())
            ->whereIn('status', ['upcoming', 'on_going'])
            ->orderBy('departure_time', 'asc')
            ->paginate(20);
        if($tripInstances->isEmpty()){
            return response()->json([
                'message' => 'No trips found matching the criteria.',
                'data' => [],
                'pagination' => [
                    'current_page' => $tripInstances->currentPage(),
                    'next_page_url' => $tripInstances->nextPageUrl(),
                    'previous_page_url' => $tripInstances->previousPageUrl(),
                    'last_page' => $tripInstances->lastPage(),
                    'per_page' => $tripInstances->perPage(),
                    'total' => $tripInstances->total(),
                ]
            ]);
        }

        $tripInstances->getCollection()->transform(function ($tripInstance){
            return [
                'trip_instance_id' => $tripInstance->id,
                'trip_location' => $tripInstance->trip->location ?? null,
                'arrival_time' => $tripInstance->arrival_time,
                'source' => $tripInstance->trip->route->source ?? null,
                'destination' => $tripInstance->trip->route->destination ?? null,
                'departure_time' => $tripInstance->departure_time,
                'status' => $tripInstance->status,
            ];
        });
        return response()->json([
            'message' => 'Trips retrieved successfully.',
            'data' => $tripInstances->items(),
            'pagination' => [
                'current_page' => $tripInstances->currentPage(),
                'next_page_url' => $tripInstances->nextPageUrl(),
                'previous_page_url' => $tripInstances->previousPageUrl(),
                'last_page' => $tripInstances->lastPage(),
                'per_page' => $tripInstances->perPage(),
                'total' => $tripInstances->total(),
            ]
        ]);
    }

    public function viewTrip($tripInstanceId){
        $user = auth('user')->user();
        if(!$user){
            return response()->json([
                'message' => 'Unauthorized'
            ]);
        }

        $tripInstance = TripInstance::with([
            'trip.bus' => fn($q) => $q->select('id', 'license_plate', 'image'),
            'trip.driver' => fn($q) => $q->select('id', 'name', 'phone'),
            'trackings' => fn($q) => $q->select('current_station_id', 'status', 'last_updated')->latest('last_updated')->take(1),
            'trip.route' => fn($q) => $q->select('id', 'name', 'source', 'destination'),
            'trip.route.routeStations.station' => fn($q) => $q->select('id', 'name', 'city'),
        ])->find($tripInstanceId);
        
        if(!$tripInstance){
            return response()->json([
                'message' => 'Trip instance not found'
            ], 404);
        }
        $lastTracking = $tripInstance->trackings->first();
        return response()->json([
            'message' => 'Trip instance retrieved successfully.',
            'data' => [
                'trip_instance_id' => $tripInstanceId,
                'trip_id' => $tripInstance->trip_id,
                'trip_location' => $tripInstance->trip->location ?? null,
                'arrival_time' => $tripInstance->arrival_time,
                'departure_time' => $tripInstance->departure_time,
                'status' => $tripInstance->status,
                'bus' => $tripInstance->trip->bus ? [
                    'license_plate' => $tripInstance->trip->bus->license_plate,
                    'image' => $tripInstance->trip->bus->image,
                ] : null,
                'driver' => $tripInstance->trip->driver ? [
                    'name' => $tripInstance->trip->driver->name,
                    'phone' => $tripInstance->trip->driver->phone,
                ] : null,
                'route' => $tripInstance->trip->route ? [
                    'name' => $tripInstance->trip->route->name,
                    'source' => $tripInstance->trip->route->source,
                    'destination' => $tripInstance->trip->route->destination,
                    'stations' => $tripInstance->trip->route->routeStations->map(function($routeStation) use ($lastTracking){
                        return [
                            'station_id' => $routeStation->station->id,
                            'name' => $routeStation->station->name,
                            'city' => $routeStation->station->city,
                            'arrival_time' => $routeStation->arrival_time,
                            'departure_time' => $routeStation->departure_time,
                            'station_order' => $routeStation->station_order,
                            'is_current' => $lastTracking && $lastTracking->current_station_id ? ($lastTracking->current_station_id == $routeStation->station_id) : false,
                        ];
                    })->sortBy('station_order')->values()->all(),
                    ] : null,
                'tracking' => $lastTracking ? [
                    'current_station_id' => $lastTracking->current_station_id,
                    'status' => $lastTracking->status,
                    'last_updated' => $lastTracking->last_updated,
                ] : [
                    'current_station_id' => null,
                    'status' => 'not_started',
                    'last_updated' => null,
                ]
            ]
        ], 200);
    }
}
