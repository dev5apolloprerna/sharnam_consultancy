<?php
 
 namespace App\Http\Controllers\Admin;
 
 use App\Http\Controllers\Controller;
 use App\Models\EmployeeLeaveMaster;
 use App\Models\EmployeeMaster;
 use App\Models\EmployeeSalaryPayment;
 use App\Models\HolidayMaster;
 use Barryvdh\DomPDF\Facade\Pdf;
 use Carbon\Carbon;
 use Illuminate\Http\Request;
 use Illuminate\Support\Facades\DB;
  use App\Services\EmployeeLeaveLedgerService;

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

         [$periodStart, $periodEnd] = $this->salaryPeriodBounds($selectedMonth, $selectedYear);
        $pendingEmployeeIds = $pendingEmployees->pluck('employee_id')->map(fn ($id) => (int) $id)->all();
        app(EmployeeLeaveLedgerService::class)->syncApprovedLeaveDebitsForPeriod($pendingEmployeeIds, $periodStart, $periodEnd);
        $leaveCounts = $this->leaveCountsByEmployee($selectedMonth, $selectedYear, $pendingEmployeeIds);

        $leaveSummaries = [];
        foreach ($pendingEmployees as $employee) {
            $leaveSummaries[$employee->employee_id] = $this->leaveSummaryForEmployee(
                (float) ($employee->basic_salary ?? 0),
                $leaveCounts[$employee->employee_id] ?? ['full_day' => 0, 'half_day' => 0],
                (int) $employee->employee_id,
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
 
        /*return view('admin.salary_process.index', compact('selectedMonth', 'selectedYear', 'pendingEmployees', 'paidSalaryRows', 'leaveCounts', 'leaveSummaries'));*/
        $defaultPaidDate = Carbon::create($selectedYear, $selectedMonth, 10)->toDateString();
        $salaryPeriodLabel = $periodStart->format('d M Y') . ' - ' . $periodEnd->format('d M Y');
        return view('admin.salary_process.index', compact('selectedMonth', 'selectedYear', 'pendingEmployees', 'paidSalaryRows', 'leaveCounts', 'leaveSummaries', 'defaultPaidDate', 'salaryPeriodLabel'));
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
       [$periodStart, $periodEnd] = $this->salaryPeriodBounds((int) $validated['salary_month'], (int) $validated['salary_year']);
       app(EmployeeLeaveLedgerService::class)->syncApprovedLeaveDebitsForPeriod($employeeIds, $periodStart, $periodEnd);
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
                $autoLeaveDeduction = $this->calculateLeaveDeduction(
                    $amount,
                    (int) $validated['salary_month'],
                    (int) $validated['salary_year'],
                    $employeeId,
                    $leaveCounts
                );
                $leaveDeduction = (float) $request->input("leave_deductions.$employeeId", $autoLeaveDeduction);

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
         $salary = EmployeeSalaryPayment::with('employee.siteAssignments.site')->findOrFail($salaryId);
        $leaveCounts = $this->leaveCountsByEmployee((int) $salary->salary_month, (int) $salary->salary_year, [(int) $salary->employee_id]);
 
         [$periodStart, $periodEnd] = $this->salaryPeriodBounds((int) $salary->salary_month, (int) $salary->salary_year);

        $salary->full_day_leave = $leaveCounts[$salary->employee_id]['full_day'] ?? 0;
        $salary->half_day_leave = $leaveCounts[$salary->employee_id]['half_day'] ?? 0;
        $leaveSummary = $this->leaveSummaryForEmployee(
            (float) $salary->amount,
            $leaveCounts[$salary->employee_id] ?? ['full_day' => 0, 'half_day' => 0],
            (int) $salary->employee_id,
            (int) $salary->salary_month,
            (int) $salary->salary_year
        );
        $salary->manual_debit_leave = $leaveSummary['manual_debit_units'];
        $salary->holiday_leave = $leaveSummary['holiday_units'];
        $salary->paid_leave = $leaveSummary['paid_leave_units'];
        $salary->chargeable_leave = $leaveSummary['chargeable_units'];


         $pdf = Pdf::loadView('pdf.employee_salary_statement', [
             'employee' => $salary->employee,
             'rows' => collect([$salary]),
             'salaryMonth' => $salary->salary_month,
             'salaryYear' => $salary->salary_year,
         ])->setPaper('a4', 'portrait');
 
         /*return $pdf->download('salary-slip-' . $salary->employee_id . '-' . $salary->salary_month . '-' . $salary->salary_year . '.pdf');*/

         $month = str_pad((string) $salary->salary_month, 2, '0', STR_PAD_LEFT);
         $employeeName = preg_replace('/\s+/', ' ', trim((string) optional($salary->employee)->employee_name));
         $fileName = $salary->salary_year . ' ' . $month . ' ' . $employeeName . '.pdf';

         return $pdf->download($fileName);
     }
 
    private function leaveCountsByEmployee(
    int $month,
    int $year,
    ?array $employeeIds = null
): array {
    [$startDate, $endDate] = $this->salaryPeriodBounds($month, $year);

    $employeeIds = $employeeIds
        ?? EmployeeMaster::pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

    if (empty($employeeIds)) {
        return [];
    }

    $approvedLeaves = EmployeeLeaveMaster::select(
            'employee_id',
            'leave_type'
        )
        ->whereIn('employee_id', $employeeIds)
        ->where('status', 'accepted')
        ->where('iStatus', 1)
        ->where('isDelete', 0)
        ->whereDate('leave_date', '>=', $startDate->toDateString())
        ->whereDate('leave_date', '<=', $endDate->toDateString())
        ->get()
        ->groupBy('employee_id');

    $counts = [];

    foreach ($employeeIds as $employeeId) {
        $fullDay = 0;
        $halfDay = 0;

        foreach ($approvedLeaves->get($employeeId, collect()) as $leave) {
            if (strtoupper((string) $leave->leave_type) === 'H') {
                $halfDay++;
            } else {
                $fullDay++;
            }
        } // Missing closing bracket was here

        $counts[$employeeId] = [
            'full_day' => $fullDay,
            'half_day' => $halfDay,
        ];
    }

    return $counts;
}

    
    private function leaveSummaryForEmployee(float $salary, array $employeeLeave, int $employeeId, int $month, int $year): array
    {
        $fullDayLeave = (float) ($employeeLeave['full_day'] ?? 0);
        $halfDayLeave = (float) ($employeeLeave['half_day'] ?? 0);
        $halfDayUnits = $halfDayLeave * 0.5;
        [$startDate, $endDate] = $this->salaryPeriodBounds($month, $year);
        $ledgerService = app(EmployeeLeaveLedgerService::class);
        $manualDebitUnits = $ledgerService->manualDebitUnitsForPeriod($employeeId, $startDate, $endDate);
                
        $holidayUnits = (float) count($this->holidayDateMap($startDate, $endDate));
        $availablePaidLeaveUnits = max(0, $ledgerService->availableUnitsForPeriod($employeeId, $startDate, $endDate));
        $totalLeaveUnits = $fullDayLeave + $halfDayUnits + $manualDebitUnits + $holidayUnits;
        $paidLeaveUnits = $availablePaidLeaveUnits + $holidayUnits;
        $excessLeaveUnits = max(0, $totalLeaveUnits - $paidLeaveUnits);

        $periodDays = $startDate->diffInDays($endDate) + 1;
        $payableDays = max(1, $periodDays - $holidayUnits);
        $perDaySalary = $salary / $payableDays;
        $leaveDeduction = round($perDaySalary * $excessLeaveUnits, 2);

        //$leaveDeduction = round($perDaySalary * $excessLeaveUnits, 2);

        return [
            'full_day' => (int) $fullDayLeave,
            'half_day' => (int) $halfDayLeave,
            'manual_debit_units' => $manualDebitUnits,
            'holiday_units' => $holidayUnits,
            'total_units' => $totalLeaveUnits,
            'available_paid_leave_units' => $availablePaidLeaveUnits,
            'paid_leave_units' => $paidLeaveUnits,
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
            $employeeId,
            $month,
            $year
        );

        return (float) $summary['leave_deduction'];
    }
    private function salaryPeriodBounds(int $month, int $year): array
    {
        $selectedDate = Carbon::create($year, $month, 1);
        $startDate = $selectedDate->copy()->subMonthsNoOverflow(2)->day(26)->startOfDay();
        $endDate = $selectedDate->copy()->subMonthNoOverflow()->day(25)->endOfDay();
        return [$startDate, $endDate];
    }

    private function holidayDateMap(Carbon $startDate, Carbon $endDate): array
    {
        $holidayDates = HolidayMaster::where('isDelete', 0)
            ->whereBetween('holiday_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip()
            ->all();

        for ($date = $startDate->copy()->startOfDay(); $date->lte($endDate); $date->addDay()) {
            if ($date->isSunday()) {
                $holidayDates[$date->toDateString()] = true;
            }
        }

        return $holidayDates;
    }
 }
