<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\TripInstance;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    //
    public function reportIssue(Request $request){
        $user = auth('user')->user();
        if(!$user){
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $validator = \Validator::make($request->all(), [
            'trip_instance_id' => 'required|integer|exists:trip_instances,id',
            'type' => 'required|string|in:bus,driver,service,other',
            'description' => 'required|string|min:1|max:255',
            'suggestions' => 'nullable|string|min:1|max:255',
            'attachment' => 'nullable|file|mimes:png,jpg,jpeg,pdf,doc,docx|max:2048',
        ]);

        if($validator->fails()){
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $attachment = null;
        if($request->hasFile('attachment') && $request->file('attachment')->isValid()){
            // store the attachment inthe storage
            $attachment = $request->file('attachment')->store('reports', 'public');
        }

        $booking = $user->bookings()->select(['trip_instance_id'])
            ->where('trip_instance_id', $request->input('trip_instance_id'))
            ->exists();
        if(!$booking){
            return response()->json([
                'message' => 'You can only report issues for trips you have booked',
            ], 403);
        }

        $trip_instance = TripInstance::select('id', 'trip_id')->find($request->input('trip_instance_id'));
        if(!$trip_instance){
            return response()->json([
                'message' => 'Trip instance not found',
            ], 404);
        }

        $report = $user->reports()->create([
            'report_number' =>  'REP-' . strtoupper(\Str::random(8)),
            'user_id' => $user->id,
            'trip_instance_id' => $trip_instance->id,
            'trip_id' => $trip_instance->trip_id,
            'type' => $request->input('type'),
            'description' => $request->input('description'),
            'suggestions' => $request->input('suggestions') ?? null,
            'status' => 'pending',
            'attachment' => $attachment ?? null,
        ]);

        return response()->json([
            'message' => 'Report submitted successfully',
            'report' => [
                'report_number' => $report->report_number,
                'trip_instance_id' => $report->trip_instance_id,
                'type' => $report->type,
                'description' => $report->description,
                'attachment' => $report->attachment ? asset('storage/' . $report->attachment) : null,
                'status' => $report->status,
                'created_at' => $report->created_at->toDateTimeString(),
            ]
        ], 201);
    }

    public function viewReports(Request $request){
        $user = auth('user')->user();
        if(!$user){
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $reports = $user->reports()->with('tripInstance.trip.route')
            ->select('id','report_number','status','trip_instance_id','created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($report){
                return [
                    'report_number' => $report->report_number,
                    'trip_instance' => $report->tripInstance ? [
                        'id' => $report->tripInstance->id,
                        'trip_name' => $report->tripInstance?->trip?->route?->name,
                    ] : null,
                    'status' => $report->status,
                    'created_at' => $report->created_at->toDateTimeString(),
                ];
            });

        return response()->json([
            'reports' => $reports,
        ], 200);
    }

    public function viewReportDetails($reportNumber){
        // trcking report
        $user = auth('user')->user();
        if(!$user){
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $report = $user->reports()->with('tripInstance.trip.route')
            ->where('report_number', $reportNumber)
            ->first();

        if(!$report){
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        return response()->json([
            'report' => [
                'report_number' => $report->report_number,
                'trip_instance' => $report->tripInstance ? [
                    'id' => $report->tripInstance->id,
                    'trip_name' => $report->tripInstance?->trip?->route?->name,
                ] : null,
                'type' => $report->type,
                'description' => $report->description,
                'suggestions' => $report->suggestions,
                'attachment' => $report->attachment ? asset('storage/' . $report->attachment) : null,
                'status' => $report->status,
                'admin_notes' => $report->admin_notes,
                'created_at' => $report->created_at->toDateTimeString(),
                'updated_at' => $report->updated_at->toDateTimeString(),
            ]
        ], 200);
    }
}
