<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCreditDebitHistory;
use App\Models\EmployeeLeaveMaster;
use App\Models\EmployeeMaster;
use App\Models\SiteAssignEmployee;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeManagerApprovalController extends Controller
{
    public function leaveList(Request $request)
    {
        $manager = auth()->guard('api')->user();
        $siteIds = $this->managerSiteIds((int) $manager->employee_id);

        $request->validate([
            'status' => 'nullable|in:pending,accepted,reject',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $data = EmployeeLeaveMaster::query()
            ->leftJoin('employee_master', 'employee_master.employee_id', '=', 'employee_leave_master.employee_id')
            ->leftJoin('construction_site_master', 'construction_site_master.site_id', '=', 'employee_leave_master.site_id')
            ->whereIn('employee_leave_master.site_id', $siteIds)
            ->where('employee_leave_master.isDelete', 0)
            ->where('employee_leave_master.iStatus', 1)
            ->when($request->filled('status'), fn ($q) => $q->where('employee_leave_master.status', $request->status))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('employee_leave_master.leave_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('employee_leave_master.leave_date', '<=', $request->to))
            ->orderByDesc('employee_leave_master.emp_leave_id')
            ->get([
                'employee_leave_master.emp_leave_id',
                'employee_leave_master.employee_id',
                'employee_master.employee_name',
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
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Manager leave list fetched successfully.',
            'data' => $data,
        ]);
    }

    public function expenseList(Request $request)
    {
        $manager = auth()->guard('api')->user();
        $siteIds = $this->managerSiteIds((int) $manager->employee_id);

        $request->validate([
            'status' => 'nullable|in:pending,accepted,reject',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $data = EmployeeCreditDebitHistory::query()
            ->leftJoin('employee_master', 'employee_master.employee_id', '=', 'employee_credit_debit_history.employee_id')
            ->whereIn('employee_credit_debit_history.site_id', $siteIds)
            ->where('employee_credit_debit_history.debit_balance', '>', 0)
            ->when($request->filled('status'), fn ($q) => $q->where('employee_credit_debit_history.status', $request->status))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('employee_credit_debit_history.date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('employee_credit_debit_history.date', '<=', $request->to))
            ->orderByDesc('employee_credit_debit_history.ledger_id')
            ->get([
                'employee_credit_debit_history.ledger_id',
                'employee_credit_debit_history.employee_id',
                'employee_master.employee_name',
                'employee_credit_debit_history.site_id',
                'employee_credit_debit_history.site_name',
                'employee_credit_debit_history.debit_balance',
                'employee_credit_debit_history.comment',
                'employee_credit_debit_history.date',
                'employee_credit_debit_history.status',
                'employee_credit_debit_history.reason',
                'employee_credit_debit_history.approved_by',
                'employee_credit_debit_history.approved_at',
                'employee_credit_debit_history.created_at',
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Manager expense list fetched successfully.',
            'data' => $data,
        ]);
    }

    public function leaveAction(Request $request, FirebaseNotificationService $firebase)
    {
        $manager = auth()->guard('api')->user();

        $request->validate([
            'emp_leave_id' => 'required|integer|exists:employee_leave_master,emp_leave_id',
            'status' => 'required|in:accepted,reject',
            'reason' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($request, $manager, $firebase) {
            $leave = EmployeeLeaveMaster::where('emp_leave_id', $request->emp_leave_id)
                ->where('isDelete', 0)
                ->lockForUpdate()
                ->first();

            if (!$leave) {
                return response()->json(['status' => false, 'message' => 'Leave not found.'], 404);
            }

            if (!$this->isManagerOfSite((int) $manager->employee_id, (int) $leave->site_id)) {
                return response()->json(['status' => false, 'message' => 'You are not manager of this site.'], 403);
            }

            if ($leave->status !== 'pending') {
                return response()->json(['status' => false, 'message' => 'This leave is already processed.'], 409);
            }

            $leave->update([
                'status' => $request->status,
                'reason' => $request->reason,
                'approved_by' => $manager->employee_id,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->status === 'accepted') {
                $this->syncLeaveAttendance($leave);
            }

            $employee = EmployeeMaster::find($leave->employee_id);
            $title = $request->status === 'accepted' ? 'Leave Approved' : 'Leave Rejected';
            $message = 'Your leave request for ' . Carbon::parse($leave->leave_date)->format('d-m-Y') . ' has been ' . $request->status . '.';

            $this->saveNotification($leave->employee_id, $manager->employee_id, 'leave_' . $request->status, $title, $message, 'employee_leave_master', $leave->emp_leave_id, [
                'emp_leave_id' => $leave->emp_leave_id,
                'status' => $request->status,
            ]);

            if ($employee && !empty($employee->device_token)) {
                $firebase->sendToTokens([$employee->device_token], $title, $message, [
                    'type' => 'leave_' . $request->status,
                    'emp_leave_id' => $leave->emp_leave_id,
                    'status' => $request->status,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Leave ' . $request->status . ' successfully.',
                'data' => $leave->fresh(),
            ]);
        });
    }

    public function expenseAction(Request $request, FirebaseNotificationService $firebase)
    {
        $manager = auth()->guard('api')->user();

        $request->validate([
            'ledger_id' => 'required|integer|exists:employee_credit_debit_history,ledger_id',
            'status' => 'required|in:accepted,reject',
            'reason' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($request, $manager, $firebase) {
            $expense = EmployeeCreditDebitHistory::where('ledger_id', $request->ledger_id)
                ->where('debit_balance', '>', 0)
                ->lockForUpdate()
                ->first();

            if (!$expense) {
                return response()->json(['status' => false, 'message' => 'Expense not found.'], 404);
            }

            if (!$this->isManagerOfSite((int) $manager->employee_id, (int) $expense->site_id)) {
                return response()->json(['status' => false, 'message' => 'You are not manager of this site.'], 403);
            }

            if ($expense->status !== 'pending') {
                return response()->json(['status' => false, 'message' => 'This expense is already processed.'], 409);
            }

            $expense->update([
                'status' => $request->status,
                'reason' => $request->reason,
                'approved_by' => $manager->employee_id,
                'approved_at' => now(),
            ]);

            $employee = EmployeeMaster::find($expense->employee_id);
            $title = $request->status === 'accepted' ? 'Expense Approved' : 'Expense Rejected';
            $message = 'Your expense of ₹' . number_format((float) $expense->debit_balance, 2) . ' has been ' . $request->status . '.';

            $this->saveNotification($expense->employee_id, $manager->employee_id, 'expense_' . $request->status, $title, $message, 'employee_credit_debit_history', $expense->ledger_id, [
                'ledger_id' => $expense->ledger_id,
                'status' => $request->status,
            ]);

            if ($employee && !empty($employee->device_token)) {
                $firebase->sendToTokens([$employee->device_token], $title, $message, [
                    'type' => 'expense_' . $request->status,
                    'ledger_id' => $expense->ledger_id,
                    'status' => $request->status,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Expense ' . $request->status . ' successfully.',
                'data' => $expense->fresh(),
            ]);
        });
    }

    private function managerSiteIds(int $managerId): array
    {
        return SiteAssignEmployee::where('site_emp_id', $managerId)
            ->where('is_site_manager', 1)
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->pluck('site_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();
    }

    private function isManagerOfSite(int $managerId, int $siteId): bool
    {
        return SiteAssignEmployee::where('site_emp_id', $managerId)
            ->where('site_id', $siteId)
            ->where('is_site_manager', 1)
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->exists();
    }

    private function syncLeaveAttendance(EmployeeLeaveMaster $leave): void
    {
        $leaveDate = Carbon::parse($leave->leave_date)->toDateString();
        $status = strtoupper($leave->leave_type) === 'H' ? 'H' : 'A';

        EmployeeAttendance::updateOrCreate(
            [
                'employee_id' => $leave->employee_id,
                'start_date_time' => Carbon::parse($leaveDate)->startOfDay(),
            ],
            [
                'site_id' => $leave->site_id,
                'status' => $status,
                'end_date_time' => Carbon::parse($leaveDate)->startOfDay(),
                'comments' => 'Auto leave (' . $status . '): ' . ($status === 'H' ? 'Half Day Leave' : 'Full Day Leave'),
                'iStatus' => 1,
                'isDelete' => 0,
                'updated_at' => now(),
            ]
        );
    }

    private function saveNotification(int $employeeId, ?int $senderId, string $type, string $title, string $message, string $table, int $referenceId, array $payload): void
    {
        DB::table('employee_notifications')->insert([
            'employee_id' => $employeeId,
            'sender_employee_id' => $senderId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'reference_table' => $table,
            'reference_id' => $referenceId,
            'payload' => json_encode($payload),
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
