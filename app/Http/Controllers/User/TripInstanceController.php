<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\TripInstance;
use Carbon\Carbon;
use App\Models\Route;

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
            ['trip' => fn($q) => $q->select('id', 'location', 'route_id'),
                        'trip.route' => fn($q) => $q->select('id', 'name', 'source', 'destination'),
                        // 'trip.route.routeStations' => fn($q) => $q->select('id', 'route_id', 'station_id', 'station_order'),
                        'trip.route.routeStations.station' => fn($q) => $q->select('id', 'name', 'city')]
            )
            ->whereHas('trip', function($q) use ($query){
                $q->where('location', 'like', "%{$query}%")
                  ->orWhereHas('route', function($q2) use ($query){
                        $q2->where('name', 'like', "%{$query}%")
                           ->orWhere('source', 'like', "%{$query}%")
                           ->orWhere('destination', 'like', "%{$query}%")
                           ->orWhereHas('routeStations.station', function($q3) use ($query){
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
                'source' => $tripInstance->trip->route->source ?? null,
                'destination' => $tripInstance->trip->route->destination ?? null,
                'arrival_time' => $tripInstance->arrival_time,
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

    public function listLines(){
        $user = auth('user')->user();
        if(!$user){
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // lines = routes
        $lines = Route::with([
            'routeStations' => fn($q) => $q->orderBy('station_order')->with('station:id,name,city'),
            'trips' => fn($q) => $q->select('id', 'route_id', 'location')->with([
            'tripInstances' => fn($q) => $q->where('departure_time', '>=', now())
                ->whereIn('status', ['upcoming', 'on_going'])
                ->orderBy('departure_time')
                ->select('id', 'trip_id', 'departure_time', 'arrival_time', 'status')
                ->take(5)
            ])
        ])
        ->select('id', 'name')
        ->paginate(10);

        if($lines->isEmpty()){
            return response()->json([
                'message' => 'No lines found.',
                'data' => [],
                'pagination' => [
                    'current_page' => $lines->currentPage(),
                    'next_page_url' => $lines->nextPageUrl(),
                    'previous_page_url' => $lines->previousPageUrl(),
                    'last_page' => $lines->lastPage(),
                    'per_page' => $lines->perPage(),
                    'total' => $lines->total(),
                ]
            ]);
        }

        $hasTrips = $lines->getCollection()->contains(function($line){
            return $line->trips->contains(function($trip){
                return $trip->tripInstances->isNotEmpty();
            });
        });

        if(!$hasTrips){
            return response()->json([
                'message' => 'No upcoming or ongoing trips found for any line.',
                'data' => [],
                'pagination' => [
                    'current_page' => $lines->currentPage(),
                    'next_page_url' => $lines->nextPageUrl(),
                    'previous_page_url' => $lines->previousPageUrl(),
                    'last_page' => $lines->lastPage(),
                    'per_page' => $lines->perPage(),
                    'total' => $lines->total(),
                ]
            ]);
        }
        return response()->json([
        'message' => 'Lines retrieved successfully.',
        'data' => $lines->getCollection()->map(function ($line) {
            return [
                'line_id' => $line->id,
                'line_name' => $line->name,
                'stations' => $line->routeStations->map(function ($routeStation) {
                    return [
                        'station_id' => $routeStation->station->id,
                        'name' => $routeStation->station->name,
                        'city' => $routeStation->station->city,
                        'arrival_time' => $routeStation->arrival_time?->toTimeString(),
                        'departure_time' => $routeStation->departure_time?->toTimeString(),
                        'station_order' => $routeStation->station_order,
                    ];
                })->values()->all(),
                'upcoming_trips' => $line->trips->flatMap(function ($trip) {
                    return $trip->tripInstances->map(function ($tripInstance) use ($trip) {
                        return [
                            'trip_instance_id' => $tripInstance->id,
                            'trip_id' => $tripInstance->trip_id,
                            'location' => $trip->location ?? null,
                            'departure_time' => $tripInstance->departure_time?->toDateTimeString(),
                            'arrival_time' => $tripInstance->arrival_time?->toDateTimeString(),
                            'status' => $tripInstance->status,
                        ];
                    });
                })->values()->all(),
                'count_trip_instances' => $line->trips->sum(function ($trip) {
                    return $trip->tripInstances->count();
                }),
            ];
        })->values()->all(),
        'pagination' => [
            'current_page' => $lines->currentPage(),
            'next_page_url' => $lines->nextPageUrl(),
            'previous_page_url' => $lines->previousPageUrl(),
            'last_page' => $lines->lastPage(),
            'per_page' => $lines->perPage(),
            'total' => $lines->total(),
        ]
    ], 200);
    }
}
