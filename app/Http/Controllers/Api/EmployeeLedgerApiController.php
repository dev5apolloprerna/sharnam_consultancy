<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMaster;
use App\Models\EmployeeCreditDebitHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeLedgerApiController extends Controller
{
    /**
     * POST /api/admin/employee-ledger/list
     * body: employee_id (required), from(optional), to(optional)
     */
    public function ledgerList(Request $request)
 {
        $request->validate([
            'employee_id' => 'required|integer|exists:employee_master,employee_id',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date',

        ]);

        $employeeId = (int) $request->employee_id;

        $employee = EmployeeMaster::select('employee_id', 'employee_name')
            ->where('employee_id', $employeeId)
            ->first();

        $rows = EmployeeCreditDebitHistory::where('employee_id', $employeeId)
            ->when($request->from, fn($q) => $q->whereDate('date', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('date', '<=', $request->to))
            ->orderBy('ledger_id', 'desc')
            ->get([
                'ledger_id',
                'credit_balance',
                'debit_balance',
                'comment',
                'date',
                'enter_by',
            ]);

        

        $totalCredit = 0.0;
        $totalDebit  = 0.0;

        $list = $rows->map(function ($r) use (&$totalCredit, &$totalDebit) {
            $debitAmount = (float) ($r->debit_balance ?? 0);
            $creditAmount = $debitAmount > 0 ? 0 : (float) $r->credit_balance;

            $totalCredit += $creditAmount;
            $totalDebit += $debitAmount;

            return [
                'ledger_id'      => $r->ledger_id,
                'credit_amount'  => round($creditAmount, 2),
                'debit_amount'   => round($debitAmount, 2),
                'comment'        => $r->comment,
                'date'           => $r->date,
                'enter_by'       => $r->enter_by,
            ];
        })->values();

        return response()->json([
            'status' => true,
            'employee' => [
                'employee_id' => $employee->employee_id,
                'employee_name' => $employee->employee_name,
            ],
            'summary' => [
                'total_credit_amount' => round($totalCredit, 2),
                'total_expense_amount' => round($totalDebit, 2),
                'total_balance' => round($totalCredit - $totalDebit, 2),
            ],
            'ledger' => $list,
        ]);
    }

    //$totalBalance = (float) ($rows->last()->credit_balance ?? 0);
    



    /**
     * POST /api/admin/employee-ledger/debit
     * body: employee_id, debit_amount, comment, date(optional), allow_negative(optional)
     */
    public function debitExpense(Request $request)
    {
        $request->validate([
            'employee_id'  => 'required|integer|exists:employee_master,employee_id',
            'debit_amount' => 'required|numeric|min:0.01',
            'comment'      => 'required|string|max:2000',
            'date'         => 'nullable|date',
        ]);

        $employeeId = (int) $request->employee_id;
        $debit      = (float) $request->debit_amount;
        $comment    = trim($request->comment);
        $date       = $request->date ?? date('Y-m-d');

        $enterBy    = Auth::id();

        DB::beginTransaction();
        try {


            // NOTE: your debit_balance is INT in schema -> rounding/casting
            $row = EmployeeCreditDebitHistory::create([
                'employee_id'    => $employeeId,
                'credit_balance' => 0,
                'debit_balance'  => $debit,
                'comment'        => $comment,
                'date'           => $date,
                'enter_by'       => $enterBy,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Expense (debit) saved successfully.',
                'ledger_id' => $row->ledger_id,
                'employee_id' => $employeeId,
                'credit_amount' => 0,
                'debit_amount' => round($debit, 2),
/*                'old_balance' => round($lastBalance, 2),
                'new_balance' => round($newBalance, 2),*/
                'date' => $date,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
        public function updateLedger(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ledger_id'       => 'required|integer|exists:employee_credit_debit_history,ledger_id',
                'credit_balance'  => 'nullable|numeric|min:0',
                'debit_balance'   => 'nullable|numeric|min:0',
                'comment'         => 'nullable|string',
                'date'            => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $ledger = EmployeeCreditDebitHistory::where('ledger_id', $request->ledger_id)->first();

            if (!$ledger) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ledger entry not found'
                ], 404);
            }

            // optional: do not allow both empty
            if (
                !$request->has('credit_balance') &&
                !$request->has('debit_balance') &&
                !$request->has('comment') &&
                !$request->has('date')
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nothing to update'
                ], 422);
            }

            DB::beginTransaction();

            if ($request->has('credit_balance')) {
                $ledger->credit_balance = $request->credit_balance;
            }

            if ($request->has('debit_balance')) {
                $ledger->debit_balance = $request->debit_balance;
            }

            if ($request->has('comment')) {
                $ledger->comment = $request->comment;
            }

            if ($request->has('date')) {
                $ledger->date = $request->date;
            }

            $ledger->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ledger updated successfully',
                'data'    => $ledger
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/employee-ledger/delete
     */
    public function deleteLedger(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ledger_id' => 'required|integer|exists:employee_credit_debit_history,ledger_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $ledger = EmployeeCreditDebitHistory::where('ledger_id', $request->ledger_id)->first();

            if (!$ledger) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ledger entry not found'
                ], 404);
            }

            DB::beginTransaction();

            $ledger->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ledger deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

}