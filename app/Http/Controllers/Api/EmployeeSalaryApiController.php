<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveMaster;
use App\Models\EmployeeMaster;
use App\Models\EmployeeSalaryPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeSalaryApiController extends Controller
{

    public function salaryList(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employee_master,employee_id',
            'salary_month' => 'nullable|integer|between:1,12',
            'salary_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $employee = EmployeeMaster::select('employee_id', 'employee_name', 'basic_salary')
            ->where('employee_id', $validated['employee_id'])
            ->first();

        $rows = EmployeeSalaryPayment::where('employee_id', $validated['employee_id'])
            ->when($request->salary_month, fn($q) => $q->where('salary_month', $request->salary_month))
            ->when($request->salary_year, fn($q) => $q->where('salary_year', $request->salary_year))
            ->orderByDesc('salary_year')
            ->orderByDesc('salary_month')
            ->orderByDesc('id')
            ->get();

        $salaryList = $rows->map(function ($row) use ($validated) {

            $leaveCounts = $this->leaveCounts(
                (int)$validated['employee_id'],
                (int)$row->salary_month,
                (int)$row->salary_year
            );

            return [
                'salary_month' => (int)$row->salary_month,
                'salary_year' => (int)$row->salary_year,
                'basic_salary' => (float)$row->amount,
                'deduct_amount' => (float)$row->deduct_amount,
                'leave_deduct_amount' => (float)($row->leave_deduct_amount ?? 0),
                'total_deduction' => (float)$row->deduct_amount + (float)($row->leave_deduct_amount ?? 0),
                'salary_amount' => (float)$row->paid_amount,
                'paid_date' => optional($row->paid_date)->format('Y-m-d'),

                'salary_slip' => $row->salary_slip_path
                    ? url('storage/' . $row->salary_slip_path)
                    : null,

                'leave' => [
                    'full_day' => $leaveCounts['full_day'],
                    'half_day' => $leaveCounts['half_day'],
                ],
            ];
        });

        return response()->json([
            'status' => true,
            'employee' => [
                'employee_id' => $employee->employee_id,
                'employee_name' => $employee->employee_name,
                'current_basic_salary' => (float)$employee->basic_salary,
            ],
            'salary' => $salaryList
        ]);
    }


    public function salaryPdfDownload(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employee_master,employee_id',
            'salary_month' => 'required|integer|between:1,12',
            'salary_year' => 'required|integer|min:2000|max:2100',
        ]);

        $employee = EmployeeMaster::select('employee_id', 'employee_name', 'basic_salary')
            ->where('employee_id', $validated['employee_id'])
            ->first();

        $row = EmployeeSalaryPayment::where('employee_id', $validated['employee_id'])
            ->where('salary_month', $validated['salary_month'])
            ->where('salary_year', $validated['salary_year'])
            ->first();

        if (!$row) {
            return response()->json([
                'status' => false,
                'message' => 'Salary record not found'
            ], 404);
        }

        $leaveCounts = $this->leaveCounts(
            $validated['employee_id'],
            $validated['salary_month'],
            $validated['salary_year']
        );

        $row->full_day_leave = $leaveCounts['full_day'];
        $row->half_day_leave = $leaveCounts['half_day'];

        $pdf = Pdf::loadView('pdf.employee_salary_statement', [
            'employee' => $employee,
            'rows' => [$row],
            'salaryMonth' => $validated['salary_month'],
            'salaryYear' => $validated['salary_year'],
        ])->setPaper('a4', 'portrait');

        $fileName = 'salary-slip-' .
            $validated['employee_id'] . '-' .
            $validated['salary_month'] . '-' .
            $validated['salary_year'] . '.pdf';

        $path = 'salary_slips/' . $fileName;

        Storage::disk('public')->put($path, $pdf->output());

        $row->salary_slip_path = $path;
        $row->save();

        return response()->json([
            'status' => true,
            'message' => 'Salary slip generated successfully',
            'salary_slip_url' => url('storage/' . $path)
        ]);
    }


    private function leaveCounts(int $employeeId, int $month, int $year): array
    {
        $rows = EmployeeLeaveMaster::select('leave_type', DB::raw('COUNT(*) as total'))
            ->where('employee_id', $employeeId)
            ->where('status', 'accepted')
            ->where('isDelete', 0)
            ->whereYear('leave_date', $year)
            ->whereMonth('leave_date', $month)
            ->groupBy('leave_type')
            ->get();

        $fullDay = 0;
        $halfDay = 0;

        foreach ($rows as $row) {

            if ($row->leave_type === 'F') {
                $fullDay = (int)$row->total;
            }

            if ($row->leave_type === 'H') {
                $halfDay = (int)$row->total;
            }
        }

        return [
            'full_day' => $fullDay,
            'half_day' => $halfDay
        ];
    }
}