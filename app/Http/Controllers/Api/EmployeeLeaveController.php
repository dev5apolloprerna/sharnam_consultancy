<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveMaster;
use App\Models\EmployeeAttendance;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\FirebaseNotificationService;
use App\Models\SiteAssignEmployee;
use App\Models\EmployeeMaster;


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
    // body: { employee_id, leave_date, leave_type(F/H; A accepted as full-day alias), comment, site_id(optional) }
   public function store(Request $request, FirebaseNotificationService $firebase)
{
    try {
        $request->validate([
            'employee_id' => 'required|exists:employee_master,employee_id',
            'leave_date'  => 'required|date',
            'leave_type'  => 'required|in:A,H,F',
            'comment'     => 'required|string',
            'site_id'     => 'required|integer|exists:construction_site_master,site_id',
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
    $siteId = (int) $request->site_id;
    $leaveType = $this->normalizeLeaveType((string) $request->leave_type);

    return DB::transaction(function () use ($employeeId, $leaveDate, $siteId, $request, $leaveType, $firebase) {
        $assigned = SiteAssignEmployee::where('site_emp_id', $employeeId)
            ->where('site_id', $siteId)
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->exists();

        if (!$assigned) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a site assigned to this employee.',
            ], 422);
        }

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

        $managers = $this->siteManagers($siteId);

        if ($managers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active site manager found for the selected site.',
            ], 422);
        }


        $leave = EmployeeLeaveMaster::create([
            'employee_id' => $employeeId,
            'site_id'     => $siteId,
            'leave_date'  => $leaveDate,
            'leave_type'  => $leaveType,
            'comment'     => $request->comment,
            'status'      => 'pending',
            'iStatus'     => 1,
            'isDelete'    => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $employee = EmployeeMaster::find($employeeId);

        $title = 'New Leave Request';
        $message = ($employee->employee_name ?? 'Employee') . ' added leave request for ' . Carbon::parse($leaveDate)->format('d-m-Y') . '.';

        foreach ($managers as $manager) {
            DB::table('employee_notifications')->insert([
                'employee_id' => $manager->employee_id,
                'sender_employee_id' => $employeeId,
                'type' => 'leave_request',
                'title' => $title,
                'message' => $message,
                'reference_table' => 'employee_leave_master',
                'reference_id' => $leave->emp_leave_id,
                'payload' => json_encode(['emp_leave_id' => $leave->emp_leave_id, 'site_id' => $siteId]),
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $tokens = $managers->pluck('device_token')->filter()->unique()->values()->all();

        DB::afterCommit(function () use ($firebase, $tokens, $title, $message, $leave, $siteId) {
            $firebase->sendToTokens($tokens, $title, $message, [
                'type' => 'leave_request',
                'emp_leave_id' => $leave->emp_leave_id,
                'site_id' => $siteId,
                'screen' => 'manager_leave_approval',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Leave request sent to site manager for approval.',
            'data'    => $leave,
            'notification' => [
                'manager_count' => $managers->count(),
                'push_token_count' => count($tokens),
            ],
        ], 201);
    });
}
private function siteManagers(int $siteId)
{
    return EmployeeMaster::query()
        ->join('site_assign_employees', 'site_assign_employees.site_emp_id', '=', 'employee_master.employee_id')
        ->where('site_assign_employees.site_id', $siteId)
        ->where('site_assign_employees.is_site_manager', 1)
        ->where('site_assign_employees.iStatus', 1)
        ->where('site_assign_employees.isDelete', 0)
        ->where('employee_master.iStatus', 1)
        ->where('employee_master.isDelete', 0)
        ->get(['employee_master.employee_id', 'employee_master.employee_name', 'employee_master.device_token']);
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
                'leave_type' => 'required|in:A,H,F',
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
        $leaveType = $this->normalizeLeaveType((string) $request->leave_type);
        $attendanceStatus = $this->attendanceStatusForLeaveType($leaveType);
        $siteId = $request->filled('site_id') ? (int) $request->site_id : 0;

        return DB::transaction(function () use ($leave, $newDate, $siteId, $request, $leaveType, $attendanceStatus) 
        {

            $oldDate = Carbon::parse($leave->leave_date)->toDateString();

            // if date changed, remove/soft-delete old auto-attendance (optional)
            if ($oldDate !== $newDate) {
                $oldAttendance = EmployeeAttendance::where('employee_id', $leave->employee_id)
                    ->whereDate('start_date_time', $oldDate)
                    ->where('isDelete', 0)
                    ->first();

                // If you only want to delete if it's "Auto absent: Leave", then check comments/status:
                if ($oldAttendance && $this->isAutoLeaveAttendance($oldAttendance)) {
                    $oldAttendance->update(['isDelete' => 1, 'updated_at' => now()]);
                }
            }

            // update leave
            $leave->update([
                'leave_date' => $newDate,
                'leave_type' => $leaveType,
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
                'status'           => $attendanceStatus,
                'start_date_time'  => Carbon::parse($newDate)->startOfDay(),
                'end_date_time'    => Carbon::parse($newDate)->startOfDay(),
                'comments'         => 'Auto leave (' . $attendanceStatus . '): ' . $this->leaveTypeLabel($leaveType),
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
        ->leftJoin('construction_site_master', 'construction_site_master.site_id', '=', 'employee_leave_master.site_id')
        ->orderBy('employee_leave_master.leave_date', 'desc')
        ->orderBy('employee_leave_master.emp_leave_id', 'desc')
        ->get([
            'employee_leave_master.emp_leave_id',
            'employee_leave_master.employee_id',
            'employee_master.employee_name',

            // added site fields
            'employee_leave_master.site_id',
            'construction_site_master.site_name',

            'employee_leave_master.leave_date',
            'employee_leave_master.leave_type',
            'employee_leave_master.comment',
            'employee_leave_master.status',
            'employee_leave_master.reason',
            'employee_leave_master.approved_by',
            'employee_leave_master.approved_at',
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
            if ($attendance && $this->isAutoLeaveAttendance($attendance)) {
                $attendance->update(['isDelete' => 1, 'updated_at' => now()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave deleted and auto leave attendance entry removed (soft).'
            ]);
        });
    }
private function normalizeLeaveType(string $leaveType): string
    {
        $leaveType = strtoupper($leaveType);

       return $leaveType === 'A' ? 'F' : $leaveType;
    }

    private function attendanceStatusForLeaveType(string $leaveType): string
    {
        return strtoupper($leaveType) === 'H' ? 'H' : 'A';
    }

    private function leaveTypeLabel(string $leaveType): string
    {
        return $leaveType === 'H' ? 'Half Day Leave' : 'Full Day Leave';
    }

    private function isAutoLeaveAttendance(EmployeeAttendance $attendance): bool
    {
        $comments = (string) $attendance->comments;

        return in_array(strtoupper((string) $attendance->status), ['A', 'H'], true)
            && ($this->startsWith($comments, 'Auto leave (')
                || $this->startsWith($comments, 'Auto absent: Leave ('));
    }

    private function startsWith(string $value, string $prefix): bool
    {
        return substr($value, 0, strlen($prefix)) === $prefix;
    }

}