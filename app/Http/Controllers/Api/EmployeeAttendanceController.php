<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLocationHistory;
use Illuminate\Validation\ValidationException;

use Carbon\Carbon;

class EmployeeAttendanceController extends Controller
{
    public function startDay(Request $request)
    {
 

        try {
            $request->validate([
                'employee_id' => 'required|exists:employee_master,employee_id',
                // 'status' => 'required|in:P,A,H,L',
                'latitude' => 'required|string',
                'longitude' => 'required|string',
                'comments' => 'nullable|string|max:100'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }


        $today = Carbon::today();

        $existing = EmployeeAttendance::whereDate('start_date_time', $today)
            ->where('employee_id', $request->employee_id)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Attendance already started for today.'], 409);
        }


        EmployeeAttendance::create([
            'employee_id' => $request->employee_id,
            'status' => 'P',
            'start_location' => $request->start_location,
            'start_date_time' => now(),
            // 'end_date_time' => now(), // placeholder
            'start_latitude' => $request->latitude,
            'start_longitude' => $request->longitude,
            'comments' => $request->comments ?? '',
            'iStatus' => 1,
            'isDelete' => 0,
        ]);

        /*EmployeeLocationHistory::create([
            'employee_id' => $request->employee_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'comments' => 'Start Day Location'
        ]);*/

        $attendance = EmployeeAttendance::where('employee_id', $request->employee_id)
            ->whereDate('start_date_time', now()->toDateString())
            ->whereNotNull('start_date_time')
            ->whereNull('end_date_time')
            ->first();
        
        $isWorkStart = $attendance ? 1 : 0;
    
       $attendance1 = EmployeeAttendance::where('employee_id', $request->employee_id)
        ->whereDate('end_date_time', now()->toDateString())
        ->whereNotNull('end_date_time')
        ->first();

        $isWorkEnd = $attendance1 ? 1 : 0;
        

        return response()->json(['success' => true, 'isWorkStart'=>$isWorkStart,'isWorkEnd'=>$isWorkEnd,'message' => 'Day started successfully.']);
    }

    public function endDay(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employee_master,employee_id',
            'latitude' => 'required|string',
            'longitude' => 'required|string',
            'comments' => 'nullable|string|max:100'
        ]);

        $today = Carbon::today();

        $attendance = EmployeeAttendance::whereDate('start_date_time', $today)
            ->where('employee_id', $request->employee_id)
            ->first();

        if (!$attendance) {
            return response()->json(['success' => false, 'message' => 'No start record found for today.'], 404);
        }
        
        $existing = EmployeeAttendance::whereDate('end_date_time', $today)
            ->where('employee_id', $request->employee_id)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Attendance already ended for today.'], 409);
        }


        $attendance->update([
            'end_location' => $request->end_location,
            'end_date_time' => now(),
            'end_latitude' => $request->latitude,
            'end_longitude' => $request->longitude,
        ]);
        
        $attendance = EmployeeAttendance::where('employee_id', $request->employee_id)
            ->whereDate('start_date_time', now()->toDateString())
            ->whereNotNull('start_date_time')
            ->whereNull('end_date_time')
            ->first();
        
        $isWorkStart = $attendance ? 1 : 0;
    
       $attendance1 = EmployeeAttendance::where('employee_id', $request->employee_id)
        ->whereDate('end_date_time', now()->toDateString())
        ->whereNotNull('end_date_time')
        ->first();

        $isWorkEnd = $attendance1 ? 1 : 0;
        

/*        EmployeeLocationHistory::create([
            'employee_id' => $request->employee_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'comments' => 'End Day Location'
        ]);*/

        return response()->json(['success' => true, 'isWorkStart'=>$isWorkStart,'isWorkEnd'=>$isWorkEnd,'message' => 'Day ended successfully.']);
    }
}
