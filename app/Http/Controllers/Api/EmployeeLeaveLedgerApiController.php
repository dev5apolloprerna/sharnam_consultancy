<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveLedger;
use App\Services\EmployeeLeaveLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->when($request->filled('from'), fn ($q) => $q->whereDate(DB::raw('COALESCE(to_date, from_date, transaction_date)'), '>=', $validated['from']))
            ->when($request->filled('to'), fn ($q) => $q->whereDate(DB::raw('COALESCE(from_date, transaction_date)'), '<=', $validated['to']))
            ->when($request->filled('entry_type'), fn ($q) => $q->where('entry_type', $validated['entry_type']))
            ->orderByRaw('COALESCE(from_date, transaction_date) DESC')
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
            'leave_units' => 'nullable|numeric|min:0.5|max:365',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'description' => 'nullable|string|max:500',
        ]);

        $fromDate = $request->filled('from_date') ? Carbon::parse($validated['from_date'])->startOfDay() : null;
        $toDate = $request->filled('to_date') ? Carbon::parse($validated['to_date'])->startOfDay() : null;

        if ($fromDate && ! $toDate) {
            $toDate = $fromDate->copy();
        }

        if (! $fromDate && $toDate) {
            $fromDate = $toDate->copy();
        }

        $leaveUnits = $fromDate && $toDate
            ? $fromDate->diffInDays($toDate) + 1
            : (float) ($validated['leave_units'] ?? 0);

        if ($leaveUnits < 0.5 || $leaveUnits > 365) {
            return response()->json([
                'success' => false,
                'message' => 'The leave units must be between 0.5 and 365.',
            ], 422);
        }


        $createdBy = auth()->user()->employee_id ?? auth()->id();
        $ledger = $ledgerService->manualAdjustment(
            (int) $validated['employee_id'],
            $validated['adjustment_type'],
            (float) $leaveUnits,
            $validated['description'] ?? null,
            $createdBy ? (int) $createdBy : null,
            $fromDate,
            $toDate
        );

        return response()->json([
            'success' => true,
            'message' => 'Manual leave ' . $validated['adjustment_type'] . ' saved successfully.',
            'current_balance' => $ledger->closing_balance,
            'data' => $ledger,
        ], 201);
    }

}
