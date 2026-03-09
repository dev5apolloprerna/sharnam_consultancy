<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMaster;
use App\Models\EmployeeCreditDebitHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeCreditController extends Controller
{
    // Form page
    public function create()
    {
        $employees = EmployeeMaster::select('employee_id', 'employee_name')
            ->orderBy('employee_name')
            ->get();

        return view('admin.employee_credit.create', compact('employees'));
    }

    // Save credit entry
    public function store(Request $request)
    {
        $request->validate([
            'employee_id'   => 'required|integer|exists:employee_master,employee_id',
            'site_id'       => 'nullable|integer|min:0',
            'credit_amount' => 'required|numeric|min:0.01',
            'comment'       => 'required|string|max:2000',
            'date'          => 'required|date',
        ]);

        $employeeId = (int) $request->employee_id;
        $siteId     = (int) ($request->site_id ?? 0);
        $amount     = (float) $request->credit_amount;
        $comment    = trim($request->comment);
        $date       = $request->date;

        // enter_by = logged-in admin user id (change guard if needed)
        $enterBy = Auth::id() ?? 0;

        DB::beginTransaction();
        try {
            // last running credit_balance (you are storing running balance in credit_balance column)
            $lastBalance = (float) EmployeeCreditDebitHistory::where('employee_id', $employeeId)
                ->orderByDesc('ledger_id')
                ->value('credit_balance');

            $newBalance = $lastBalance + $amount;

            EmployeeCreditDebitHistory::create([
                'employee_id'     => $employeeId,
                'site_id'         => $siteId,
                'credit_balance'  => $newBalance,
                'debit_balance'   => 0,
                'comment'         => $comment,
                'date'            => $date,
                'enter_by'        => $enterBy,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.employee-credit.create')
                ->with('success', 'Credit added successfully. New Balance: ' . number_format($newBalance, 2));
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    // Optional: list ledger
    public function index(Request $request)
    {
        $qEmployee = $request->employee_id;

        $employees = EmployeeMaster::select('employee_id', 'employee_name')
            ->orderBy('employee_name')
            ->get();

        $rows = EmployeeCreditDebitHistory::with('employee')
            ->when($qEmployee, fn($qq) => $qq->where('employee_id', $qEmployee))
            ->orderByDesc('ledger_id')
            ->paginate(20);

        return view('admin.employee_credit.index', compact('rows', 'employees', 'qEmployee'));
    }
}