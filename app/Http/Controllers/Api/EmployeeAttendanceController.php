<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLocationHistory;
use Carbon\Carbon;

class EmployeeAttendanceController extends Controller
{
    public function startDay(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employee_master,employee_id',
            'status' => 'required|in:P,A,H,L',
            'comments' => 'nullable|string|max:100'
        ]);

        $today = Carbon::today();

        $existing = EmployeeAttendance::whereDate('start_date_time', $today)
            ->where('employee_id', $request->employee_id)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Attendance already started for today.'], 409);
        }

        EmployeeAttendance::create([
            'employee_id' => $request->employee_id,
            'status' => $request->status,
            'start_date_time' => now(),
            'end_date_time' => now(), // temporary, will update on end
            'comments' => $request->comments ?? '',
            'iStatus' => 1,
            'isDelete' => 0,
        ]);

        EmployeeLocationHistory::create([
            'employee_id' => $request->employee_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'comments' => $request->comments
        ])

        return response()->json(['success' => true, 'message' => 'Day started successfully.']);
    }

    public function endDay(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employee_master,employee_id',
        ]);

        $today = Carbon::today();

        $attendance = EmployeeAttendance::whereDate('start_date_time', $today)
            ->where('employee_id', $request->employee_id)
            ->first();

        if (!$attendance) {
            return response()->json(['success' => false, 'message' => 'No start record found for today.'], 404);
        }

        $attendance->update([
            'end_date_time' => now()
        ]);

        EmployeeLocationHistory::create([
            'employee_id' => $request->employee_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'comments' => $request->comments
        ])


        return response()->json(['success' => true, 'message' => 'Day ended successfully.']);
    }
}
