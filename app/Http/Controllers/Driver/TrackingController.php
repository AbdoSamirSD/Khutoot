<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TripInstance;
use Validator;
use App\Models\Station;
class TrackingController extends Controller
{
    //

    public function updateStatus(Request $request, $tripInstanceId)
    {
        // Update the status of the trip instance

        $driver = auth('driver')->user();
        if (!$driver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_station_id' => 'required|exists:stations,id',
            'status' => 'required|in:delayed,arrived,departed',
        ]);

        $current_station_id = $request->input('current_station_id');

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $status = $request->input('status');
        

        // check if the trip instance belongs to the authenticated driver
        $tripInstance = TripInstance::with('trip.route.routeStations.station')
            ->whereHas('trip', function($q) use ($driver){
                $q->where('driver_id', $driver->id);
            })
            ->where('status', 'on_going')
            ->first();
        if (!$tripInstance || $tripInstance->id != $tripInstanceId) {
            return response()->json(['error' => 'Trip instance not found or does not belong to the driver.'], 404);
        }

        // check if the current_station_id is the first station in the route
        $firstStation = $tripInstance->trip->route->routeStations()
            ->orderBy('station_order', 'asc')
            ->first();
            if ($current_station_id == $firstStation->station_id && $status === 'arrived') {
                return response()->json(['error' => 'Trip has not started yet. Please start trip first'], 400);
            }
            
            
        $lastTracking = $tripInstance->trackings()->latest('last_updated')->first();
        if (!$lastTracking) {
            return response()->json(['error' => 'No tracking history found. Ensure the trip has been started properly.'], 400);
        }
        $routeStations = $tripInstance->trip->route->routeStations()->orderBy('station_order', 'asc')->pluck('station_id')->toArray();
        $lastStation  = end($routeStations);

        if ($lastTracking && $lastTracking->current_station_id === $lastStation) {
            if ($status === 'departed') {
                return response()->json([
                    'error' => 'You cannot depart from the last station. Trip should be ended instead.'
                ], 422);
            }
        }
        
        // check if the current_station_id is part of the trip's route
        if (!in_array($current_station_id, $routeStations)) {
            return response()->json(['error' => 'The specified station is not part of the trip\'s route.'], 400);
        } 
        
        
        if($status === 'delayed'){
            $lastStationIndex = array_search($lastTracking->current_station_id, $routeStations);
            $nextStationId = $routeStations[$lastStationIndex + 1] ?? null;

            if(!in_array($current_station_id, [$lastTracking->current_station_id, $nextStationId])){
                return response()->json(['error' => 'Delay can only be reported for the current or the next station.'], 422);
            }

            $tripInstance->trackings()->create([
                'trip_instance_id' => $tripInstanceId,
                'current_station_id' => $current_station_id,
                'status' => $status,
                'last_updated' => now(),
            ]);

            return response()->json(['message' => 'Trip instance updated to delayed status successfully.'], 200);
        }
        
        
        
        if ($lastTracking) {
            if (in_array($lastTracking->status, ['arrived', 'delayed'])) {
                if (!($status === 'departed' && $lastTracking->current_station_id == $current_station_id)) {
                    return response()->json(['error' => 'You must depart from the same station after arriving.'], 422);
                }
            } elseif ($lastTracking->status === 'departed') {
                $lastStationIndex = array_search($lastTracking->current_station_id, $routeStations);
                $nextStationId = $routeStations[$lastStationIndex + 1] ?? null;

                if (!($status === 'arrived' && $current_station_id == $nextStationId)) {
                    return response()->json(['error' => 'You must arrive at the next station in sequence.'], 422);
                }
            }
        }
        $tripInstance->trackings()->create([
            'trip_instance_id' => $tripInstanceId,
            'current_station_id' => $current_station_id,
            'status' => $status,
            'last_updated' => now(),
        ]);

        // notify users which booked from the next station to be implemented later

        return response()->json(['message' => 'Trip instance updated successfully. current station: ' . Station::find($current_station_id)->name . ' and its status: ' . $status], 200);
    }
}
