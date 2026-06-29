<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveMaster;
use App\Models\EmployeeMaster;
use Illuminate\Http\Request;
use App\Services\EmployeeLeaveLedgerService;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeLeaveManagerController extends Controller
{
    /**
     * Manager site-wise assigned employee leave list
     */
    public function managerEmployeeLeaveList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status'      => 'nullable|in:pending,accepted,reject',
            'leave_date'  => 'nullable|date',
            'employee_id' => 'nullable|integer|exists:employee_master,employee_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $loginEmployeeId = auth()->user()->employee_id ?? auth()->id();

        $managerAssign = DB::table('site_assign_employees')
            ->where('site_emp_id', $loginEmployeeId)
            ->where('is_site_manager', 1)
            ->first();

        if (!$managerAssign) {
            return response()->json([
                'success' => false,
                'message' => 'Only site manager can view employee leave list',
            ], 403);
        }

        $manager = EmployeeMaster::where('employee_id', $loginEmployeeId)->first();
        $siteId = $managerAssign->site_id;

        $query = EmployeeLeaveMaster::query()
            ->join('employee_master', 'employee_master.employee_id', '=', 'employee_leave_master.employee_id')
            ->join('site_assign_employees', function ($join) use ($siteId) {
                $join->on('site_assign_employees.site_emp_id', '=', 'employee_leave_master.employee_id')
                    ->where('site_assign_employees.site_id', '=', $siteId);
            })
            ->where('employee_leave_master.employee_id', '!=', $loginEmployeeId)
            ->where('employee_leave_master.iStatus', 1)
            ->where('employee_leave_master.isDelete', 0)
            ->select(
                'employee_leave_master.emp_leave_id',
                'employee_leave_master.employee_id',
                'employee_master.employee_name',
                'site_assign_employees.site_id',
                'site_assign_employees.is_site_manager',
                'employee_leave_master.leave_date',
                'employee_leave_master.leave_type',
                'employee_leave_master.comment',
                'employee_leave_master.reason',
                'employee_leave_master.status',
                'employee_leave_master.approved_by',
                'employee_leave_master.created_at',
                'employee_leave_master.updated_at'
            )
            ->distinct();

        if ($request->filled('status')) {
            $query->where('employee_leave_master.status', $request->status);
        }

        if ($request->filled('leave_date')) {
            $query->whereDate('employee_leave_master.leave_date', $request->leave_date);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_leave_master.employee_id', $request->employee_id);
        }

        $rows = $query->orderBy('employee_leave_master.emp_leave_id', 'desc')->get();

        $data = $rows->map(function ($row) {
            return [
                'emp_leave_id'     => $row->emp_leave_id,
                'employee_id'      => $row->employee_id,
                'employee_name'    => $row->employee_name,
                'site_id'          => $row->site_id,
                'is_site_manager'  => (int) $row->is_site_manager,
                'leave_date'       => $row->leave_date,
                'leave_type'       => $row->leave_type,
                'comment'          => $row->comment,
                'reason'           => $row->reason,
                'status'           => $row->status ?: 'pending',
                'status_text'      => ucfirst($row->status ?: 'pending'),
                'approved_by'      => $row->approved_by,
                'created_at'       => $row->created_at,
                'updated_at'       => $row->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Site-wise employee leave list fetched successfully',
            'manager' => [
                'employee_id'      => $loginEmployeeId,
                'employee_name'    => $manager->employee_name ?? '',
                'site_id'          => $siteId,
                'is_site_manager'  => 1,
            ],
            'count' => $data->count(),
            'data'  => $data,
        ]);
    }

    /**
     * Manager approve or reject employee leave
     */
    public function managerEmployeeLeaveAction(Request $request, EmployeeLeaveLedgerService $ledgerService, FirebaseNotificationService $firebase)
    {
        $validator = Validator::make($request->all(), [
            'emp_leave_id' => 'required|integer|exists:employee_leave_master,emp_leave_id',
            'status'       => 'required|in:accepted,reject',
            'reason'       => 'nullable|string|max:500',
        ], [
            'status.in' => 'Status must be accepted or reject.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $loginEmployeeId = auth()->user()->employee_id ?? auth()->id();

        $managerAssign = DB::table('site_assign_employees')
            ->where('site_emp_id', $loginEmployeeId)
            ->where('is_site_manager', 1)
            ->first();

        if (!$managerAssign) {
            return response()->json([
                'success' => false,
                'message' => 'Only site manager can approve or reject leave',
            ], 403);
        }

        $siteId = $managerAssign->site_id;

        $leave = EmployeeLeaveMaster::where('emp_leave_id', $request->emp_leave_id)
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->first();

        if (!$leave) {
            return response()->json([
                'success' => false,
                'message' => 'Leave record not found',
            ], 404);
        }

        $leaveEmployeeAssign = DB::table('site_assign_employees')
            ->where('site_emp_id', $leave->employee_id)
            ->where('site_id', $siteId)
            ->first();

        if (!$leaveEmployeeAssign) {
            return response()->json([
                'success' => false,
                'message' => 'You can only approve or reject leave for employees of your assigned site',
            ], 403);
        }

        if ((int) $leave->employee_id === (int) $loginEmployeeId) {
            return response()->json([
                'success' => false,
                'message' => 'Manager cannot approve or reject own leave',
            ], 403);
        }

        $currentStatus = $leave->status ?: 'pending';

        if (in_array($currentStatus, ['accepted', 'reject'])) {
            return response()->json([
                'success' => false,
                'message' => 'This leave is already ' . $currentStatus,
            ], 409);
        }

        $leave->status = $request->status;
        $leave->approved_by = $loginEmployeeId;

        if ($request->filled('reason')) {
            $leave->reason = $request->reason;
        }

        $leave->save();

        if ($leave->status === 'accepted') {
            $ledgerService->debitApprovedLeave($leave, (int) $loginEmployeeId);
        }

        $employee = EmployeeMaster::find($leave->employee_id);
        $title = $leave->status === 'accepted' ? 'Leave Approved' : 'Leave Rejected';
        $statusText = $leave->status === 'accepted' ? 'approved' : 'rejected';
        $message = 'Your leave request for ' . Carbon::parse($leave->leave_date)->format('d-m-Y') . ' has been ' . $statusText . '.';
        $payload = [
            'type' => 'leave_' . $leave->status,
            'emp_leave_id' => $leave->emp_leave_id,
            'status' => $leave->status,
            'leave_date' => Carbon::parse($leave->leave_date)->toDateString(),
        ];

        DB::table('employee_notifications')->insert([
            'employee_id' => $leave->employee_id,
            'sender_employee_id' => $loginEmployeeId,
            'type' => 'leave_' . $leave->status,
            'title' => $title,
            'message' => $message,
            'reference_table' => 'employee_leave_master',
            'reference_id' => $leave->emp_leave_id,
            'payload' => json_encode($payload),
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($employee && !empty($employee->device_token)) {
            $firebase->sendToTokens([$employee->device_token], $title, $message, $payload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave ' . $leave->status . ' successfully',
            'data' => [
                'emp_leave_id' => $leave->emp_leave_id,
                'employee_id'  => $leave->employee_id,
                'status'       => $leave->status,
                'status_text'  => ucfirst($leave->status),
                'reason'       => $leave->reason,
                'approved_by'  => $leave->approved_by,
                'updated_at'   => $leave->updated_at,
            ],
        ]);
    }
}