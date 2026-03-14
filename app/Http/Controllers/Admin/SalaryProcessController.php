<?php
 
 namespace App\Http\Controllers\Admin;
 
 use App\Http\Controllers\Controller;
 use App\Models\EmployeeAttendance;
 use App\Models\EmployeeMaster;
 use App\Models\EmployeeSalaryPayment;
 use Barryvdh\DomPDF\Facade\Pdf;
 use Carbon\Carbon;
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
 

        $pendingEmployeeIds = $pendingEmployees->pluck('employee_id')->map(fn ($id) => (int) $id)->all();
        $leaveCounts = $this->leaveCountsByEmployee($selectedMonth, $selectedYear, $pendingEmployeeIds);

        $leaveSummaries = [];
        foreach ($pendingEmployees as $employee) {
            $leaveSummaries[$employee->employee_id] = $this->leaveSummaryForEmployee(
                (float) ($employee->basic_salary ?? 0),
                $leaveCounts[$employee->employee_id] ?? ['full_day' => 0, 'half_day' => 0],
                $selectedMonth,
                $selectedYear
            );
        }
 
         $paidSalaryRows = EmployeeSalaryPayment::with('employee:employee_id,employee_name')
             ->where('salary_month', $selectedMonth)
             ->where('salary_year', $selectedYear)
             ->orderByDesc('paid_date')
             ->orderByDesc('id')
             ->get();
 
        return view('admin.salary_process.index', compact('selectedMonth', 'selectedYear', 'pendingEmployees', 'paidSalaryRows', 'leaveCounts', 'leaveSummaries'));
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
/*-            'leave_deductions' => 'nullable|array',
-            'leave_deductions.*' => 'nullable|numeric|min:0',*/
         ], [
             'selected_employee_ids.required' => 'Please select at least one employee.',
             'paid_date.required' => 'Please select paid date.',
         ]);
 
         $employeeIds = array_values(array_unique(array_map('intval', $validated['selected_employee_ids'])));
         $employees = EmployeeMaster::whereIn('employee_id', $employeeIds)->get()->keyBy('employee_id');
       $leaveCounts = $this->leaveCountsByEmployee((int) $validated['salary_month'], (int) $validated['salary_year'], $employeeIds);
 
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
/*-                $leaveDeduction = (float) ($request->input("leave_deductions.$employeeId", 0));*/
                $leaveDeduction = $this->calculateLeaveDeduction(
                    $amount,
                    (int) $validated['salary_month'],
                    (int) $validated['salary_year'],
                    $employeeId,
                    $leaveCounts
                );

                 $totalDeduction = $deduction + $leaveDeduction;
                 $netAmount = $amount - $totalDeduction;
 
                 if ($netAmount < 0) {
                     DB::rollBack();
                     return back()->withInput()->withErrors([
/*-                        "leave_deductions.$employeeId" => "Total deduction cannot be greater than salary for {$employees[$employeeId]->employee_name}.",*/
                        "deductions.$employeeId" => "Total deduction cannot be greater than salary for {$employees[$employeeId]->employee_name}.",
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
/*-        $leaveCounts = $this->leaveCountsByEmployee((int) $salary->salary_month, (int) $salary->salary_year);*/
        $leaveCounts = $this->leaveCountsByEmployee((int) $salary->salary_month, (int) $salary->salary_year, [(int) $salary->employee_id]);
 
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
 
    private function leaveCountsByEmployee(int $month, int $year, ?array $employeeIds = null): array
     {

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = (int) $endDate->day;

        $employeeIds = $employeeIds ?? EmployeeMaster::pluck('employee_id')->map(fn ($id) => (int) $id)->all();
        if (empty($employeeIds)) {
            return [];
        }

        $records = EmployeeAttendance::select('employee_id', 'status', 'comments', 'start_date_time')
            ->whereIn('employee_id', $employeeIds)
             ->where('isDelete', 0)
            ->whereDate('start_date_time', '>=', $startDate->toDateString())
            ->whereDate('start_date_time', '<=', $endDate->toDateString())
            ->orderBy('start_date_time')
             ->get();
 
        $dailyUnits = [];
        foreach ($records as $record) {
            $employeeId = (int) $record->employee_id;
            $date = Carbon::parse($record->start_date_time)->toDateString();
            $unit = $this->leaveUnitFromAttendance($record->status, (string) ($record->comments ?? ''));

            $existing = $dailyUnits[$employeeId][$date] ?? null;
            if ($existing === null || $unit < $existing) {
                $dailyUnits[$employeeId][$date] = $unit;
             }

        }

        $counts = [];
        foreach ($employeeIds as $employeeId) {
            $fullDay = 0;
            $halfDay = 0;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($year, $month, $day)->toDateString();
                $unit = $dailyUnits[$employeeId][$date] ?? 1.0; // if attendance missing => full leave

                if ($unit >= 1) {
                    $fullDay++;
                } elseif ($unit > 0) {
                    $halfDay++;
                }
             }

            $counts[$employeeId] = [
                'full_day' => $fullDay,
                'half_day' => $halfDay,
            ];
         }
 
         return $counts;
     }

    private function leaveUnitFromAttendance(?string $status, string $comments): float
    {
        $status = strtoupper((string) $status);
        $comments = strtoupper($comments);

        if ($status === 'P') {
            return 0.0;
        }

        if ($status === 'H' || str_contains($comments, 'LEAVE (H)') || str_contains($comments, 'HALF')) {
            return 0.5;
        }

        if ($status === 'A' || str_contains($comments, 'LEAVE (F)') || str_contains($comments, 'FULL')) {
            return 1.0;
        }

        return 1.0;
    }

    private function leaveSummaryForEmployee(float $salary, array $employeeLeave, int $month, int $year): array
{
    $fullDayLeave = (float) ($employeeLeave['full_day'] ?? 0);
    $halfDayLeave = (float) ($employeeLeave['half_day'] ?? 0);

    $totalLeaveUnits = $fullDayLeave + ($halfDayLeave * 0.5);

    $freeLeaveUnits = 2.0;
    $excessLeaveUnits = max(0, $totalLeaveUnits - $freeLeaveUnits);

    $daysInMonth = max(1, cal_days_in_month(CAL_GREGORIAN, $month, $year));
    $perDaySalary = $salary / $daysInMonth;

    $leaveDeduction = round($perDaySalary * $excessLeaveUnits, 2);

    return [
        'full_day' => (int) $fullDayLeave,
        'half_day' => (int) $halfDayLeave,
        'total_units' => $totalLeaveUnits,
        'free_units' => $freeLeaveUnits,
        'chargeable_units' => $excessLeaveUnits,
        'per_day_salary' => $perDaySalary,
        'leave_deduction' => $leaveDeduction,
    ];
}

    private function calculateLeaveDeduction(float $salary, int $month, int $year, int $employeeId, ?array $leaveCounts = null): float
    {
        $counts = $leaveCounts ?? $this->leaveCountsByEmployee($month, $year, [$employeeId]);
        $summary = $this->leaveSummaryForEmployee(
            $salary,
            $counts[$employeeId] ?? ['full_day' => 0, 'half_day' => 0],
            $month,
            $year
        );

        return (float) $summary['leave_deduction'];
    }
 }
