<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Salary Slip</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 0;
        }

        .slip {
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .slip td,
        .slip th {
            border: 1px dashed #333;
            padding: 5px 6px;
            vertical-align: middle;
            word-break: break-word;
        }

        .slip .solid {
            border-style: solid;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
        }

        .company {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
        }

        .heading {
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            background: #f3f3f3;
        }

        .label {
            font-weight: 400;
        }

        .value {
            font-weight: 600;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .total-row td {
            font-weight: 700;
            border-style: solid;
        }

        .net-label,
        .net-value {
            background: #c9e3ef;
            font-weight: 700;
        }

        .net-label {
            text-align: center;
        }

        .net-value {
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $row = collect($rows)->first();

    $salaryMonthLabel = date('F', mktime(0, 0, 0, (int) $salaryMonth, 10));

    $employeeName = $employee->employee_name ?? '-';
    $employeeId = $employee->employee_id ?? '-';
    $department = $employee->department ?? '-';
    $designation = $employee->designation ?? '-';
        $assignedSites = collect();

    if ($employee && method_exists($employee, 'relationLoaded') && $employee->relationLoaded('siteAssignments')) {
        $assignedSites = $employee->siteAssignments
            ->pluck('site.site_name')
            ->filter()
            ->unique()
            ->values();
    }

    $assignedSite = $assignedSites->isNotEmpty()
        ? $assignedSites->implode(', ')
        : ($employee->assigned_site ?? ($employee->site_name ?? '-'));
        
    $accountOrUpi = $employee->account_no ?? ($employee->upi_id ?? '-');

    $joiningDate = '-';
    if (!empty($employee->joining_date)) {
        try {
            $joiningDate = \Carbon\Carbon::parse($employee->joining_date)->format('d-m-Y');
        } catch (\Throwable $e) {
            $joiningDate = (string) $employee->joining_date;
        }
    }

    $workingDays = (int) now()->setDate((int) $salaryYear, (int) $salaryMonth, 1)->daysInMonth;
    $fullDayLeave = (float) ($row->full_day_leave ?? 0);
    $halfDayLeave = (float) ($row->half_day_leave ?? 0);
    $manualDebitLeave = (float) ($row->manual_debit_leave ?? 0);
    $holidayLeave = (float) ($row->holiday_leave ?? 0);
    $paidLeave = (float) ($row->paid_leave ?? 0);
    $lwpDays = (float) ($row->chargeable_leave ?? 0);

    $basicSalary = (float) ($row->amount ?? 0);
    $mealAllowance = 0;
    $transportAllowance = 0;
    $foodAllowance = 0;

    $leaveDeduction = (float) ($row->leave_deduct_amount ?? 0);
    $professionalTax = (float) ($row->deduct_amount ?? 0);
    $totalEarnings = $basicSalary + $mealAllowance + $transportAllowance + $foodAllowance;
    $totalDeduction = $leaveDeduction + $professionalTax;

    $netPay = (float) ($row->paid_amount ?? 0);
    $paymentDate = optional($row->paid_date)->format('d-m-Y') ?: '-';
    $paymentMode = $row->payment_mode ?? 'Bank Transfer';
@endphp

<table class="slip">
    <colgroup>
        <col style="width: 21%;">
        <col style="width: 29%;">
        <col style="width: 21%;">
        <col style="width: 29%;">
    </colgroup>

    <tr>
        <td colspan="4" class="title solid">Salary Slip</td>
    </tr>
    <tr>
        <td colspan="4" class="company solid">Sharanam Civil Consultancy</td>
    </tr>

    <tr>
        <td class="label">Name:</td>
        <td class="value">{{ $employeeName }}</td>
        <td class="label">Employee ID:</td>
        <td class="value">{{ $employeeId }}</td>
    </tr>
    <tr>
        <td class="label">Department:</td>
        <td class="value">{{ $department }}</td>
        <td class="label">Designation:</td>
        <td class="value">{{ $designation }}</td>
    </tr>
    <tr>
        <td class="label">Date of Joining:</td>
        <td class="value">{{ $joiningDate }}</td>
        <td class="label">Assigned Site:</td>
        <td class="value">{{ $assignedSite }}</td>
    </tr>
    <tr>
        <td class="label">AC No/UPI ID:</td>
        <td class="value">{{ $accountOrUpi }}</td>
        <td class="label">Salary for Month:</td>
        <td class="value">{{ $salaryMonthLabel }} {{ $salaryYear }}</td>
    </tr>
    <tr>
        <td class="label">Total Days:</td>
        <td class="value">{{ $workingDays }}</td>
        <td class="label">LWP Days:</td>
        <td class="value">{{ number_format($lwpDays, 1) }}</td>
    </tr>
 <tr>
        <td class="label">Manual Ledger Leave:</td>
        <td class="value">{{ number_format($manualDebitLeave, 1) }}</td>
        <td class="label">Paid Holiday Leave:</td>
        <td class="value">{{ number_format($holidayLeave, 1) }}</td>
    </tr>
    <tr>
        <td class="label">Paid Leave Allowance:</td>
        <td class="value">{{ number_format($paidLeave, 1) }}</td>
        <td class="label"></td>
        <td class="value"></td>
    </tr>
    <tr>
        <th colspan="2" class="heading solid">Description</th>
        <th class="heading solid">Earnings</th>
        <th class="heading solid">Deductions</th>
    </tr>

    <tr>
        <td colspan="2">Basic Salary</td>
        <td class="right">{{ number_format($basicSalary, 2) }}</td>
        <td class="right">-</td>
    </tr>
    <tr>
        <td colspan="2">Meal Allowance</td>
        <td class="right">{{ number_format($mealAllowance, 2) }}</td>
        <td class="right">-</td>
    </tr>
    <tr>
        <td colspan="2">Transportation Allowance</td>
        <td class="right">{{ number_format($transportAllowance, 2) }}</td>
        <td class="right">-</td>
    </tr>
    <tr>
        <td colspan="2">Food Allowance</td>
        <td class="right">{{ number_format($foodAllowance, 2) }}</td>
        <td class="right">-</td>
    </tr>
    <tr>
        <td colspan="2">Leave Without Pay (LWP)</td>
        <td class="right">-</td>
        <td class="right">{{ number_format($leaveDeduction, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Professional Tax</td>
        <td class="right">-</td>
        <td class="right">{{ number_format($professionalTax, 2) }}</td>
    </tr>

    <tr class="total-row">
        <td colspan="2" class="right">Total</td>
        <td class="right">{{ number_format($totalEarnings, 2) }}</td>
        <td class="right">{{ number_format($totalDeduction, 2) }}</td>
    </tr>

    <tr>
        <td class="label">Payment Date:</td>
        <td class="value">{{ $paymentDate }}</td>
        <td class="net-label">Net Pay</td>
        <td class="net-value">{{ number_format($netPay, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Payment Mode:</td>
        <td class="value">{{ $paymentMode }}</td>
        <td colspan="2"></td>
    </tr>
</table>

</body>
</html>
