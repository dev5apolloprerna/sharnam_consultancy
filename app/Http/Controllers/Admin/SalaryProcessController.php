<?php
 
 namespace App\Http\Controllers\Admin;
 
 use App\Http\Controllers\Controller;
 use App\Models\EmployeeAttendance;
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
          $salary->manual_debit_leave = app(EmployeeLeaveLedgerService::class)->manualDebitUnitsForPeriod((int) $salary->employee_id, $periodStart, $periodEnd);

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
 
    private function leaveCountsByEmployee(int $month, int $year, ?array $employeeIds = null): array
     {
       /* $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = (int) $endDate->day;*/
        [$startDate, $endDate] = $this->salaryPeriodBounds($month, $year);
        $holidayDates = $this->holidayDateMap($startDate, $endDate);

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
 
        $dailyWorkedUnits = [];
        foreach ($records as $record) {
            $employeeId = (int) $record->employee_id;
            $date = Carbon::parse($record->start_date_time)->toDateString();
            $leaveUnit = $this->leaveUnitFromAttendance($record->status, (string) ($record->comments ?? ''));
            $workedUnit = max(0, 1 - $leaveUnit);
            $dailyWorkedUnits[$employeeId][$date] = min(1.0, ($dailyWorkedUnits[$employeeId][$date] ?? 0) + $workedUnit);
        }

        $counts = [];
        foreach ($employeeIds as $employeeId) {
            $fullDay = 0;
            $halfDay = 0;

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateString = $date->toDateString();
                if (isset($holidayDates[$dateString])) {
                    continue;
                }
                $unit = isset($dailyWorkedUnits[$employeeId][$dateString]) ? max(0, 1 - $dailyWorkedUnits[$employeeId][$dateString]) : 1.0; // if attendance missing => full leave

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

        if (in_array($status, ['P', 'L'], true)) {
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

    private function leaveSummaryForEmployee(float $salary, array $employeeLeave, int $employeeId, int $month, int $year): array
    {
        $fullDayLeave = (float) ($employeeLeave['full_day'] ?? 0);
        $halfDayLeave = (float) ($employeeLeave['half_day'] ?? 0);
        $halfDayUnits = $halfDayLeave * 0.5;
        [$startDate, $endDate] = $this->salaryPeriodBounds($month, $year);
        $ledgerService = app(EmployeeLeaveLedgerService::class);
        $manualDebitUnits = $ledgerService->manualDebitUnitsForPeriod($employeeId, $startDate, $endDate);
        $totalLeaveUnits = $fullDayLeave + $halfDayUnits + $manualDebitUnits;

/*        $freeLeaveUnits = 2.0;
        $excessLeaveUnits = max(0, $totalLeaveUnits - $freeLeaveUnits);
*/
        $freeLeaveUnits = $ledgerService->availableUnitsForPeriod($employeeId, $startDate, $endDate);
        $excessLeaveUnits = max(0, $totalLeaveUnits - $freeLeaveUnits);
        $periodDays = $startDate->diffInDays($endDate) + 1;
        $holidayDays = count($this->holidayDateMap($startDate, $endDate));
        $payableDays = max(1, $periodDays - $holidayDays);
        $perDaySalary = $salary / $payableDays;
        $leaveDeduction = round($perDaySalary * $excessLeaveUnits, 2);

        //$leaveDeduction = round($perDaySalary * $excessLeaveUnits, 2);

        return [
            'full_day' => (int) $fullDayLeave,
            'half_day' => (int) $halfDayLeave,
            'manual_debit_units' => $manualDebitUnits,
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
