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
                'site_id' => 'required',
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

/*        $existing = EmployeeAttendance::whereDate('start_date_time', $today)
            ->where('employee_id', $request->employee_id)->where('site_id',$request->site_id)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Attendance already started for today.'], 409);
        }*/


        $created = EmployeeAttendance::create([
            'employee_id' => $request->employee_id,
            'site_id' => $request->site_id,
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
        


        return response()->json(['success' => true, 'attendance_id' => $created->attendence_id,'isWorkStart'=>$isWorkStart,'message' => 'Day started successfully.']);
    }

    public function endDay(Request $request)
    {
        $request->validate([
            'employee_id'    => 'required|exists:employee_master,employee_id',
            'attendance_id'  => 'required|integer',
            'site_id'        => 'required',
            'latitude'       => 'required|string',
            'longitude'      => 'required|string',
            'comments'       => 'nullable|string|max:100'
        ]);
    
        // Find attendance by ID + employee (and optional site)
        $attendance = EmployeeAttendance::where('attendence_id', $request->attendance_id)   // <-- if PK is attendance_id, change this
            ->where('employee_id', $request->employee_id)
            ->where('site_id', $request->site_id)
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->first();
    
        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found.'
            ], 404);
        }
    
        // If already ended, stop
        if (!empty($attendance->end_date_time)) {
            return response()->json([
                'success' => false,
                'isWorkStart' => 0,
                'message' => 'Day already ended for this attendance id.'
            ], 409);
        }
    
        // Update end info
        $attendance->update([
            'end_location'  => $request->end_location,
            'end_date_time' => now(),
            'end_latitude'  => $request->latitude,
            'end_longitude' => $request->longitude,
            // optional if you store comments on end too:
            // 'comments'      => $request->comments ?? $attendance->comments,
        ]);
    
        return response()->json([
            'success'       => true,
            'attendance_id' => $attendance->id,   // <-- if PK is attendance_id, change this
            'isWorkStart'   => 0,
            'message'       => 'Day ended successfully.'
        ]);
    }
}
