<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Http;
use Illuminate\Http\Request;
use App\Models\TripInstance;
use App\Models\Booking;
use Validator;
class TrackingController extends Controller
{
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
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $current_station_id = $request->input('current_station_id');
        $status = $request->input('status');

        // check if the trip instance belongs to the authenticated driver
        $tripInstance = TripInstance::with('trip.route.routeStations.station')
            ->whereHas('trip', function($q) use ($driver){
                $q->where('driver_id', $driver->id);
            })
            ->where('status', 'on_going')
            ->where('id', $tripInstanceId)
            ->first();
        if (!$tripInstance) {
            return response()->json(['error' => 'Trip instance not found or does not belong to the driver.'], 404);
        }

        // check if the current_station_id is the first station in the route
        $routeStations = $tripInstance->trip->route->routeStations()->orderBy('station_order')->get();
        if($routeStations->isEmpty()){
            return response()->json(['error' => 'No stations found for this trip route.'], 400);
        }

        $stationIds = $routeStations->pluck('station_id')->toArray();
        $firstStationId = $routeStations->first()->station_id;
        $lastStationId = $routeStations->last()->station_id;

        if (!in_array($current_station_id, $stationIds)) {
            return response()->json(['error' => 'The specified station is not part of the trip\'s route.'], 400);
        }

        if ($current_station_id === $firstStationId && $status === 'arrived') {
            return response()->json(['error' => 'Trip has not started yet. Please start trip first.'], 400);
        }
        
        if ($current_station_id === $lastStationId && $status === 'departed') {
            return response()->json(['error' => 'You cannot depart from the last station. Trip should be ended instead.'], 422);
        }
            
        $lastTracking = $tripInstance->trackings()->latest('last_updated');
        if (!$lastTracking) {
            return response()->json(['error' => 'No tracking history found. Ensure the trip has been started properly.'], 400);
        }

        $lastStationIndex  = array_search($lastTracking->current_station_id, $stationIds);
        $nextStationId = $stationIds[$lastStationIndex + 1] ?? null;
        
        if ($status === 'delayed' && !in_array($current_station_id, [$lastTracking->current_station_id, $nextStationId])) {
            return response()->json(['error' => 'Delay can only be reported for the current or the next station.'], 422);
        }

        if ($status === 'arrived' && $lastTracking->status === 'departed' && $current_station_id !== $nextStationId) {
            return response()->json(['error' => 'You must arrive at the next station in sequence.'], 422);
        }

        if ($status === 'departed' && in_array($lastTracking->status, ['arrived', 'delayed']) && $lastTracking->current_station_id !== $current_station_id) {
            return response()->json(['error' => 'You must depart from the same station after arriving.'], 422);
        }
        
        $tripInstance->trackings()->create([
            'trip_instance_id' => $tripInstanceId,
            'current_station_id' => $current_station_id,
            'status' => $status,
            'last_updated' => now(),
        ]);

        $currentStation = $routeStations->firstWhere('station_id', $current_station_id);
        $nextStation = $nextStationId ? $routeStations->firstWhere('station_id', $nextStationId) : null;
        $stationName = $currentStation->station->name;
        $currentStationOrder = $currentStation->station_order;

        // notify users if trip delayed
        if ($status === 'delayed'){
            $playerIds = Booking::where('trip_instance_id', $tripInstanceId)
                ->where('start_station_order', '>=', $currentStationOrder)
                ->join('users', 'bookings.user_id', '=', 'users.id')
                ->pluck('users.player_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            $this->sendNotification($playerIds, "Your trip has been delayed at {$stationName}.");
        }
        // notify users which booked from the next station to be implemented later
        if ($status === 'departed' && $nextStation){
            // notify users which booked from the next station            
            $playerIds = $this->getPlayerIds($tripInstanceId, [$nextStation->station_order]);
            $this->sendNotification($playerIds, "Your trip has departed from {$stationName}.");
        }

        if ($status === 'arrived'){
            $playerIds = $this->getPlayerIds($tripInstanceId, [$currentStationOrder]);
            $this->sendNotification($playerIds, "Your trip has arrived at {$stationName}.");
        }

        // make seats valid when user arrive to his station to be implemented later 
        //
        //
        //
        //
        //
        return response()->json(['message' => 'Trip instance updated successfully. current station: ' . $stationName . ' and its status: ' . $status], 200);
    }

    protected function getPlayerIds(int $tripInstanceId, array $stationOrders){
        return Booking::where('trip_instance_id', $tripInstanceId)
            ->whereIn('start_station_order', $stationOrders)
            ->join('users', 'bookings.user_id', '=', 'users.id')
            ->pluck('users.player_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function sendNotification($playerIds, $message){
        try{
            if(!empty($playerIds)){
                Http::withHeaders([
                    'Authorization' => 'Key ' . env('ONESIGNAL_REST_API_KEY'),
                    'Content-Type' => 'application/json'
                ])->post(env('ONESIGNAL_REST_API_URL') . '/notifications', [
                    'app_id' => env('ONESIGNAL_APP_ID'),
                    'include_player_ids' => $playerIds,
                    'headings' => ['en' => 'Trip Update'],
                    'contents' => ['en' => $message],
                ]);
            }
        } catch (\Exception $e){
            \Log::error('OneSignal notification failed: ' . $e->getMessage());
        }
    }
}
