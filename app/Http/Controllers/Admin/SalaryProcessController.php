<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveMaster;
use App\Models\EmployeeMaster;
use App\Models\EmployeeSalaryPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryProcessController extends Controller
{
    public function index(Request $request)
    {
        $selectedMonth = (int) ($request->salary_month ?: now()->month);
        $selectedYear = (int) ($request->salary_year ?: now()->year);

        $paidEmployeeIds = EmployeeSalaryPayment::where('salary_month', $selectedMonth)
            ->where('salary_year', $selectedYear)
            ->pluck('employee_id');

        $pendingEmployees = EmployeeMaster::select('employee_id', 'employee_name', 'basic_salary')
            ->whereNotIn('employee_id', $paidEmployeeIds)
            ->orderBy('employee_name')
            ->get();

        $leaveCounts = $this->leaveCountsByEmployee($selectedMonth, $selectedYear);

        $paidSalaryRows = EmployeeSalaryPayment::with('employee:employee_id,employee_name')
            ->where('salary_month', $selectedMonth)
            ->where('salary_year', $selectedYear)
            ->orderByDesc('paid_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.salary_process.index', compact('selectedMonth', 'selectedYear', 'pendingEmployees', 'paidSalaryRows', 'leaveCounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|integer|between:1,12',
            'salary_year' => 'required|integer|min:2000|max:2100',
            'paid_date' => 'required|date',
            'selected_employee_ids' => 'required|array|min:1',
            'selected_employee_ids.*' => 'required|integer|exists:employee_master,employee_id',
            'deductions' => 'nullable|array',
            'deductions.*' => 'nullable|numeric|min:0',
            'leave_deductions' => 'nullable|array',
            'leave_deductions.*' => 'nullable|numeric|min:0',
        ], [
            'selected_employee_ids.required' => 'Please select at least one employee.',
            'paid_date.required' => 'Please select paid date.',
        ]);

        $employeeIds = array_values(array_unique(array_map('intval', $validated['selected_employee_ids'])));
        $employees = EmployeeMaster::whereIn('employee_id', $employeeIds)->get()->keyBy('employee_id');

        DB::beginTransaction();
        try {
            foreach ($employeeIds as $employeeId) {
                if (!isset($employees[$employeeId])) {
                    continue;
                }

                $alreadyPaid = EmployeeSalaryPayment::where('employee_id', $employeeId)
                    ->where('salary_month', $validated['salary_month'])
                    ->where('salary_year', $validated['salary_year'])
                    ->exists();

                if ($alreadyPaid) {
                    continue;
                }

                $amount = (float) ($employees[$employeeId]->basic_salary ?? 0);
                $deduction = (float) ($request->input("deductions.$employeeId", 200));
                $leaveDeduction = (float) ($request->input("leave_deductions.$employeeId", 0));
                $totalDeduction = $deduction + $leaveDeduction;
                $netAmount = $amount - $totalDeduction;

                if ($netAmount < 0) {
                    DB::rollBack();
                    return back()->withInput()->withErrors([
                        "leave_deductions.$employeeId" => "Total deduction cannot be greater than salary for {$employees[$employeeId]->employee_name}.",
                    ]);
                }

                EmployeeSalaryPayment::create([
                    'employee_id' => $employeeId,
                    'salary_month' => $validated['salary_month'],
                    'salary_year' => $validated['salary_year'],
                    'amount' => $amount,
                    'deduct_amount' => $deduction,
                    'leave_deduct_amount' => $leaveDeduction,
                    'paid_amount' => $netAmount,
                    'paid_date' => $validated['paid_date'],
                ]);
            }

            DB::commit();
            return redirect()->route('admin.salary-process.index', [
                'salary_month' => $validated['salary_month'],
                'salary_year' => $validated['salary_year'],
            ])->with('success', 'Selected employee salaries paid successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function downloadSlip($salaryId)
    {
        $salary = EmployeeSalaryPayment::with('employee:employee_id,employee_name,basic_salary')->findOrFail($salaryId);
        $leaveCounts = $this->leaveCountsByEmployee((int) $salary->salary_month, (int) $salary->salary_year);

        $salary->full_day_leave = $leaveCounts[$salary->employee_id]['full_day'] ?? 0;
        $salary->half_day_leave = $leaveCounts[$salary->employee_id]['half_day'] ?? 0;

        $pdf = Pdf::loadView('pdf.employee_salary_statement', [
            'employee' => $salary->employee,
            'rows' => collect([$salary]),
            'salaryMonth' => $salary->salary_month,
            'salaryYear' => $salary->salary_year,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('salary-slip-' . $salary->employee_id . '-' . $salary->salary_month . '-' . $salary->salary_year . '.pdf');
    }

    private function leaveCountsByEmployee(int $month, int $year): array
    {
        $rows = EmployeeLeaveMaster::select('employee_id', 'leave_type', DB::raw('COUNT(*) as total'))
            ->where('status', 'accepted')
            ->where('isDelete', 0)
            ->whereYear('leave_date', $year)
            ->whereMonth('leave_date', $month)
            ->groupBy('employee_id', 'leave_type')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $employeeId = (int) $row->employee_id;
            if (!isset($counts[$employeeId])) {
                $counts[$employeeId] = ['full_day' => 0, 'half_day' => 0];
            }
            if ($row->leave_type === 'F') {
                $counts[$employeeId]['full_day'] = (int) $row->total;
            }
            if ($row->leave_type === 'H') {
                $counts[$employeeId]['half_day'] = (int) $row->total;
            }
        }

        return $counts;
    }
}
