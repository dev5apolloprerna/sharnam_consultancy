<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveLedger;
use App\Models\EmployeeMaster;
use App\Services\EmployeeLeaveLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeLeaveLedgerController extends Controller
{
    public function index(Request $request, EmployeeLeaveLedgerService $ledgerService)
    {
        $selectedEmployeeId = $request->filled('employee_id') ? (int) $request->employee_id : null;

        $employees = EmployeeMaster::select('employee_id', 'employee_name')
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->orderBy('employee_name')
            ->get();

        $ledgerRows = EmployeeLeaveLedger::with('employee:employee_id,employee_name', 'leave:emp_leave_id,leave_date,leave_type,status')
            ->when($selectedEmployeeId, fn ($query) => $query->where('employee_id', $selectedEmployeeId))
            ->orderByDesc('transaction_date')
            ->orderByDesc('leave_ledger_id')
            ->limit(100)
            ->get();

        $currentBalance = $selectedEmployeeId ? $ledgerService->currentBalance($selectedEmployeeId) : null;

        return view('admin.employee_leave_ledger.index', compact(
            'employees',
            'ledgerRows',
            'selectedEmployeeId',
            'currentBalance'
        ));
    }

    public function manualAdjustment(Request $request, EmployeeLeaveLedgerService $ledgerService)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employee_master,employee_id',
            'adjustment_type' => 'required|in:credit,debit',
            'leave_units' => 'required|numeric|min:0.5|max:365',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string|max:500',
        ]);

        $ledgerService->manualAdjustment(
            (int) $validated['employee_id'],
            $validated['adjustment_type'],
            (float) $validated['leave_units'],
            $request->filled('transaction_date') ? Carbon::parse($validated['transaction_date']) : null,
            $validated['description'] ?? null,
            auth()->id() ? (int) auth()->id() : null
        );

        return redirect()
            ->route('admin.employee-leave-ledger.index', ['employee_id' => $validated['employee_id']])
            ->with('success', 'Manual leave ' . $validated['adjustment_type'] . ' saved successfully.');
    }

    public function syncApprovedLeaveDebits(Request $request, EmployeeLeaveLedgerService $ledgerService)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employee_master,employee_id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ]);

        $created = $ledgerService->syncApprovedLeaveDebitsForEmployee(
            (int) $validated['employee_id'],
            $request->filled('from_date') ? Carbon::parse($validated['from_date']) : null,
            $request->filled('to_date') ? Carbon::parse($validated['to_date']) : null
        );

        $message = $created > 0
            ? 'Approved leave debit sync completed. Entries created: ' . $created . '.'
            : 'No new approved leave debit entries found for this employee.';

        return redirect()
            ->route('admin.employee-leave-ledger.index', ['employee_id' => $validated['employee_id']])
            ->with('success', $message);
    }

    public function monthlyCredit(Request $request, EmployeeLeaveLedgerService $ledgerService)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer|exists:employee_master,employee_id',
            'credit_month' => 'required|integer|between:1,12',
            'credit_year' => 'required|integer|min:2000|max:2100',
            'credit_units' => 'required|numeric|min:0.5|max:365',
        ]);

        $monthDate = Carbon::create((int) $validated['credit_year'], (int) $validated['credit_month'], 1)->startOfMonth();
        $creditUnits = (float) $validated['credit_units'];
        $created = 0;
        $selectedEmployeeId = $request->filled('employee_id') ? (int) $validated['employee_id'] : null;

        if ($selectedEmployeeId) {
            $ledger = $ledgerService->creditMonthlyLeave($selectedEmployeeId, $monthDate, $creditUnits);
            $created = $ledger->wasRecentlyCreated ? 1 : 0;
        } else {
            $created = $ledgerService->creditMonthlyLeaves($monthDate, $creditUnits);
        }

        $message = $created > 0
            ? 'Monthly leave credit completed. Entries created: ' . $created . '.'
            : ($selectedEmployeeId
                ? 'Monthly leave credit already exists for the selected employee/month.'
                : 'Monthly leave credit already exists for all active employees in the selected month.');

        return redirect()
            ->route('admin.employee-leave-ledger.index', $selectedEmployeeId ? ['employee_id' => $selectedEmployeeId] : [])
            ->with('success', $message);
    }
}
