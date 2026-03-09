<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveMaster;
use App\Models\EmployeeAttendance;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeLeaveController extends Controller
{
    // GET /api/employee/leaves?employee_id=1&from=2026-03-01&to=2026-03-31
    public function index(Request $request)
    {
        $q = EmployeeLeaveMaster::query()
            ->where('isDelete', 0)
            ->where('iStatus', 1);

        if ($request->filled('employee_id')) {
            $q->where('employee_id', $request->employee_id);
        }

        if ($request->filled('from')) {
            $q->whereDate('leave_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $q->whereDate('leave_date', '<=', $request->to);
        }

        $data = $q->orderBy('leave_date', 'desc')->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    // POST /api/employee/leaves
    // body: { employee_id, leave_date, leave_type(F/H), comment, site_id(optional) }
    public function store(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employee_master,employee_id',
                'leave_date'  => 'required|date',
                'leave_type'  => 'required|in:F,H',
                'comment'     => 'required|string',
                'site_id'     => 'nullable|integer', // optional, fallback below
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);
        }

        $leaveDate = Carbon::parse($request->leave_date)->toDateString();
        $employeeId = (int) $request->employee_id;

        // If you don't have employee->site mapping, require site_id OR default it to 0
        $siteId = $request->filled('site_id') ? (int) $request->site_id : 0;

        // NOTE: MyISAM doesn't support transactions; still safe logically, but best to use InnoDB if possible.
        return DB::transaction(function () use ($employeeId, $leaveDate, $siteId, $request) {

            // Prevent duplicate leave same date
            $already = EmployeeLeaveMaster::where('employee_id', $employeeId)
                ->whereDate('leave_date', $leaveDate)
                ->where('isDelete', 0)
                ->first();

            if ($already) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave already exists for this date.'
                ], 409);
            }

            $leave = EmployeeLeaveMaster::create([
                'employee_id' => $employeeId,
                'leave_date'  => $leaveDate,
                'leave_type'  => $request->leave_type,
                'comment'     => $request->comment,
                'iStatus'     => 1,
                'isDelete'    => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            /**
             * AUTO ATTENDANCE:
             * Requirement: if employee is on leave => attendance status "A" automatically.
             * We will insert/update attendance record for that date with status "A".
             *
             * (Optional idea: for half day you could use 'H' or 'L', but you asked A)
             */
            $attendance = EmployeeAttendance::where('employee_id', $employeeId)
                ->whereDate('start_date_time', $leaveDate)
                ->where('isDelete', 0)
                ->first();

            $payload = [
                'employee_id'      => $employeeId,
                'site_id'          => $siteId,
                'status'           => 'A',
                'start_date_time'  => Carbon::parse($leaveDate)->startOfDay(), // 00:00:00
                'end_date_time'    => Carbon::parse($leaveDate)->startOfDay(),
                'comments'         => 'Auto absent: Leave (' . $request->leave_type . ')',
                'iStatus'          => 1,
                'isDelete'         => 0,
                'updated_at'       => now(),
            ];

            if ($attendance) {
                // If someone already punched P for that date, you can block leave OR override.
                // Here we override to A as per requirement.
                $attendance->update($payload);
            } else {
                $payload['created_at'] = now();
                EmployeeAttendance::create($payload);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave created and attendance marked absent automatically.',
                'data'    => $leave
            ]);
        });
    }

    // GET /api/employee/leaves/{id}
    public function show(Request $request)
    {
        $id=$request->emp_leave_id;
        $leave = EmployeeLeaveMaster::where('emp_leave_id', $id)
            ->where('isDelete', 0)
            ->first();

        if (!$leave) {
            return response()->json(['success' => false, 'message' => 'Leave not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $leave]);
    }

    // PUT /api/employee/leaves/{id}
    // allow changing leave_date/type/comment and keep attendance in sync
    public function update(Request $request)
    {
        try {
            $request->validate([
                'leave_date' => 'required|date',
                'leave_type' => 'required|in:F,H',
                'comment'    => 'required|string',
                'site_id'    => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);
        }
        $id=$request->emp_leave_id;
        $leave = EmployeeLeaveMaster::where('emp_leave_id', $id)
            ->where('isDelete', 0)
            ->first();

        if (!$leave) {
            return response()->json(['success' => false, 'message' => 'Leave not found'], 404);
        }

        $newDate = Carbon::parse($request->leave_date)->toDateString();
        $siteId = $request->filled('site_id') ? (int) $request->site_id : 0;

        return DB::transaction(function () use ($leave, $newDate, $siteId, $request) {

            $oldDate = Carbon::parse($leave->leave_date)->toDateString();

            // if date changed, remove/soft-delete old auto-attendance (optional)
            if ($oldDate !== $newDate) {
                $oldAttendance = EmployeeAttendance::where('employee_id', $leave->employee_id)
                    ->whereDate('start_date_time', $oldDate)
                    ->where('isDelete', 0)
                    ->first();

                // If you only want to delete if it's "Auto absent: Leave", then check comments/status:
                if ($oldAttendance && $oldAttendance->status === 'A') {
                    $oldAttendance->update(['isDelete' => 1, 'updated_at' => now()]);
                }
            }

            // update leave
            $leave->update([
                'leave_date' => $newDate,
                'leave_type' => $request->leave_type,
                'comment'    => $request->comment,
                'updated_at' => now(),
            ]);

            // upsert attendance for new date
            $attendance = EmployeeAttendance::where('employee_id', $leave->employee_id)
                ->whereDate('start_date_time', $newDate)
                ->where('isDelete', 0)
                ->first();

            $payload = [
                'employee_id'      => $leave->employee_id,
                'site_id'          => $siteId,
                'status'           => 'A',
                'start_date_time'  => Carbon::parse($newDate)->startOfDay(),
                'end_date_time'    => Carbon::parse($newDate)->startOfDay(),
                'comments'         => 'Auto absent: Leave (' . $request->leave_type . ')',
                'iStatus'          => 1,
                'isDelete'         => 0,
                'updated_at'       => now(),
            ];

            if ($attendance) $attendance->update($payload);
            else {
                $payload['created_at'] = now();
                EmployeeAttendance::create($payload);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave updated and attendance synced.',
                'data'    => $leave
            ]);
        });
    }
    public function leaveList(Request $request)
{
    $request->validate([
        'employee_id' => 'nullable|integer',
        'status'      => 'nullable|in:pending,accepted,reject',
        'from'        => 'nullable|date',
        'to'          => 'nullable|date',
    ]);

    $base = EmployeeLeaveMaster::query()
        ->where('employee_leave_master.isDelete', 0)
        ->where('employee_leave_master.iStatus', 1);

    // optional filters
    if ($request->filled('employee_id')) {
        $base->where('employee_leave_master.employee_id', (int)$request->employee_id);
    }
    if ($request->filled('status')) {
        $base->where('employee_leave_master.status', $request->status);
    }
    if ($request->filled('from')) {
        $base->whereDate('employee_leave_master.leave_date', '>=', $request->from);
    }
    if ($request->filled('to')) {
        $base->whereDate('employee_leave_master.leave_date', '<=', $request->to);
    }

    // counts (same filters, but status-wise)
    $countsQuery = clone $base;

    $counts = [
        'pending'  => (clone $countsQuery)->where('employee_leave_master.status', 'pending')->count(),
        'accepted' => (clone $countsQuery)->where('employee_leave_master.status', 'accepted')->count(),
        'reject'   => (clone $countsQuery)->where('employee_leave_master.status', 'reject')->count(),
        'total'    => (clone $countsQuery)->count(),
    ];

    // list with employee name (adjust table/columns if different)
    $data = $base
        ->leftJoin('employee_master', 'employee_master.employee_id', '=', 'employee_leave_master.employee_id')
        ->orderBy('employee_leave_master.leave_date', 'desc')
        ->orderBy('employee_leave_master.emp_leave_id', 'desc')
        ->get([
            'employee_leave_master.emp_leave_id',
            'employee_leave_master.employee_id',
            'employee_master.employee_name', // change if your column name is different
            'employee_leave_master.leave_date',
            'employee_leave_master.leave_type',
            'employee_leave_master.comment',
            'employee_leave_master.status',
            'employee_leave_master.reason',
            'employee_leave_master.created_at',
            'employee_leave_master.updated_at',
        ]);

    return response()->json([
        'success' => true,
        'counts'  => $counts,
        'data'    => $data,
    ]);
}

    // DELETE /api/employee/leaves/{id}
    // soft-delete leave and soft-delete auto attendance for that date
    public function destroy(Request $request)
    {
        $id=$request->emp_leave_id;
        $leave = EmployeeLeaveMaster::where('emp_leave_id', $id)
            ->where('isDelete', 0)
            ->first();

        if (!$leave) {
            return response()->json(['success' => false, 'message' => 'Leave not found'], 404);
        }

        return DB::transaction(function () use ($leave) {
            $leaveDate = Carbon::parse($leave->leave_date)->toDateString();

            $leave->update([
                'isDelete'   => 1,
                'updated_at' => now(),
            ]);

            $attendance = EmployeeAttendance::where('employee_id', $leave->employee_id)
                ->whereDate('start_date_time', $leaveDate)
                ->where('isDelete', 0)
                ->first();

            // Remove only if it's absent due to leave (you can tighten this condition as you want)
            if ($attendance && $attendance->status === 'A') {
                $attendance->delete();
            }

            $leave->delete();

            return response()->json([
                'success' => true,
                'message' => 'Leave deleted and attendance entry removed (soft).'
            ]);
        });
    }
}