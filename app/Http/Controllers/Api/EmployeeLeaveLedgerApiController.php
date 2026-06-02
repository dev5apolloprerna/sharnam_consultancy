<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveLedger;
use App\Services\EmployeeLeaveLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeLeaveLedgerApiController extends Controller
{
    public function ledgerList(Request $request, EmployeeLeaveLedgerService $ledgerService)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employee_master,employee_id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'entry_type' => 'nullable|in:monthly_credit,leave_debit,manual_credit,manual_debit',
        ]);

        $rows = EmployeeLeaveLedger::with('leave:emp_leave_id,leave_date,leave_type,status')
            ->where('employee_id', (int) $validated['employee_id'])
            ->when($request->filled('from'), fn ($q) => $q->whereDate('transaction_date', '>=', $validated['from']))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('transaction_date', '<=', $validated['to']))
            ->when($request->filled('entry_type'), fn ($q) => $q->where('entry_type', $validated['entry_type']))
            ->orderByDesc('transaction_date')
            ->orderByDesc('leave_ledger_id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Leave ledger history fetched successfully.',
            'employee_id' => (int) $validated['employee_id'],
            'current_balance' => $ledgerService->currentBalance((int) $validated['employee_id']),
            'count' => $rows->count(),
            'data' => $rows,
        ]);
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

        $createdBy = auth()->user()->employee_id ?? auth()->id();
        $ledger = $ledgerService->manualAdjustment(
            (int) $validated['employee_id'],
            $validated['adjustment_type'],
            (float) $validated['leave_units'],
            $request->filled('transaction_date') ? Carbon::parse($validated['transaction_date']) : null,
            $validated['description'] ?? null,
            $createdBy ? (int) $createdBy : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Manual leave ' . $validated['adjustment_type'] . ' saved successfully.',
            'current_balance' => $ledger->closing_balance,
            'data' => $ledger,
        ], 201);
    }

}
