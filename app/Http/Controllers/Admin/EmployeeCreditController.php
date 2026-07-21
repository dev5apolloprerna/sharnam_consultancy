<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMaster;
use App\Models\EmployeeCreditDebitHistory;
use App\Models\SiteAssignEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeCreditController extends Controller
{
    // Form page
    public function create()
    {
        $employees = EmployeeMaster::with(['siteAssignments.site'])
            ->select('employee_id', 'employee_name','member_id')
            ->orderBy('employee_name')
            ->get();

        $employeeSites = $employees->mapWithKeys(function ($employee) {
            return [
                $employee->employee_id => $employee->siteAssignments
                    ->filter(fn($assignment) => $assignment->site)
                    ->map(fn($assignment) => [
                        'site_id' => $assignment->site->site_id,
                        'site_name' => $assignment->site->site_name,
                    ])
                    ->values(),
            ];
        });

        return view('admin.employee_credit.create', compact('employees', 'employeeSites'));
    }

    // Save credit entry
    // Save simple credit/debit entry
    public function store(Request $request)
    {
        $request->validate([
            'employee_id'   => 'required|integer|exists:employee_master,employee_id',
            'site_id'       => 'required|integer|exists:construction_site_master,site_id',
            'credit_amount' => 'nullable|numeric|min:0.01',
            'debit_amount'  => 'nullable|numeric|min:0.01',
            'comment'       => 'required|string|max:2000',
            'date'          => 'required|date',
        ]);

        $employeeId = (int) $request->employee_id;
        $siteId     = (int) $request->site_id;
        //$amount     = (float) $request->credit_amount;
        $credit     = (float) ($request->credit_amount ?? 0);
        $debit      = (float) ($request->debit_amount ?? 0);
        $comment    = trim($request->comment);
        $date       = $request->date;

        $siteName   = SiteAssignEmployee::where('site_emp_id', $employeeId)
            ->where('site_id', $siteId)
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->whereHas('site')
            ->with('site')
            ->first()?->site?->site_name;

        if (!$siteName) {
            return back()
                ->withInput()
                ->withErrors(['site_id' => 'Please select a site assigned to the selected employee.']);
        }


        if ($credit <= 0 && $debit <= 0) {
            return back()
                ->withInput()
                ->withErrors(['amount' => 'Please enter credit or debit amount.']);
        }

        if ($credit > 0 && $debit > 0) {
            return back()
                ->withInput()
                ->withErrors(['amount' => 'Please enter either credit or debit, not both.']);
        }


        // enter_by = logged-in admin user id (change guard if needed)
        $enterBy = Auth::id() ?? 0;

        DB::beginTransaction();
        try {
            // last running credit_balance (you are storing running balance in credit_balance column)
           
            EmployeeCreditDebitHistory::create([
                'employee_id'     => $employeeId,
                'site_id'         => $siteId,
                'site_name'       => $siteName,
                'credit_balance'  => $credit,
                'debit_balance'   => $debit,
                'comment'         => $comment,
                'date'            => $date,
                'enter_by'        => $enterBy,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.employee-credit.create')
                ->with('success', 'Entry saved successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    // Optional: list ledger
    public function index(Request $request)
    {
        $qEmployee = $request->employee_id;

        $employees = EmployeeMaster::select('employee_id', 'employee_name','member_id')
            ->orderBy('employee_name')
            ->get();

        $ledgerQuery = EmployeeCreditDebitHistory::with(['employee', 'site', 'enteredBy', 'enteredByEmployee', 'approvedBy', 'approvedByUser'])
            ->when($qEmployee, fn($qq) => $qq->where('employee_id', $qEmployee));


        $allRows = (clone $ledgerQuery)
            ->orderBy('date')
            ->orderBy('ledger_id')
            ->get();

        $runningBalance = 0.0;
        $totalCredit = 0.0;
        $totalDebit = 0.0;
        $runningBalances = [];

        foreach ($allRows as $ledgerRow) {
            $creditAmount = (float) ($ledgerRow->credit_balance ?? 0);
            $debitAmount = $ledgerRow->status === 'reject'
                ? 0.0
                : (float) ($ledgerRow->debit_balance ?? 0);

            $totalCredit += $debitAmount > 0 ? 0.0 : $creditAmount;
            $totalDebit += $debitAmount;

            /*
             * Older mobile expense entries saved the post-expense balance in
             * credit_balance and the expense amount in debit_balance. When both
             * columns have values, treat credit_balance as that row's resulting
             * balance instead of adding it again as a new credit.
             */
            if ($debitAmount > 0 && $creditAmount > 0) {
                $runningBalance = $creditAmount;
            } else {
                $runningBalance += $creditAmount;
                $runningBalance -= $debitAmount;
            }
            $runningBalances[$ledgerRow->ledger_id] = $runningBalance;
        }
        $totalBalance = $totalCredit - $totalDebit;
        $rows = $ledgerQuery
            ->orderByDesc('date')
            ->orderByDesc('ledger_id')
            ->paginate(20);

        return view('admin.employee_credit.index', compact('rows', 'employees', 'qEmployee', 'totalCredit', 'totalDebit', 'totalBalance', 'runningBalances'));
    }
   public function updateExpenseStatus(Request $request)
    {
        $validated = $request->validate([
            'ledger_id' => 'required|integer|exists:employee_credit_debit_history,ledger_id',
            'status' => 'required|in:accepted,reject',
            'reason' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($validated) {
            $expense = EmployeeCreditDebitHistory::where('ledger_id', $validated['ledger_id'])
                ->where('debit_balance', '>', 0)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expense->status === $validated['status']) {
                abort(422, 'This expense already has the selected status.');
            }

            $expense->update([
                'status' => $validated['status'],
                'reason' => $validated['status'] === 'reject' ? ($validated['reason'] ?? null) : null,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });

        return back()->with('success', 'Expense ' . $validated['status'] . ' successfully.');
    }
}