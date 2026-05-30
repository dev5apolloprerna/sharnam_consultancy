<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Slip - {{ $employee->employee_name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 24px; color: #1f2937; font-size: 13px; background: #ffffff; }
        .slip { border: 1px solid #d1d5db; border-radius: 10px; overflow: hidden; }
        .header { background: #0f172a; color: #ffffff; padding: 20px; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 6px 0 0; font-size: 12px; color: #cbd5e1; }
        .section { padding: 18px 20px; }
        .section-title { font-size: 14px; margin: 0 0 10px; color: #111827; border-left: 4px solid #0f172a; padding-left: 8px; }
        .meta-table,.salary-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 7px 0; vertical-align: top; }
        .salary-table th,.salary-table td { border: 1px solid #d1d5db; padding: 10px; }
        .salary-table thead th { background: #f3f4f6; text-align: left; font-size: 12px; }
        .text-right { text-align: right; }
        .net-row td { font-weight: 700; background: #ecfeff; }
        .footer { padding: 14px 20px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #6b7280; }
    </style>
</head>
<body>
    @php
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
@endphp
    <div class="slip">
        <div class="header">
            <h1>Salary Slip</h1>
            <p>Period: {{ $periodLabel }} | Generated on: {{ $issuedOn }}</p>
        </div>

        <div class="section">
            <h2 class="section-title">Employee Details</h2>
            <table class="meta-table">
                <tr>
                    <td><strong>Employee ID:</strong> {{ $employee->employee_id }}</td>
                    <td><strong>Employee Name:</strong> {{ $employee->employee_name }}</td>
                </tr>
                <tr>
                    <td><strong>Email:</strong> {{ $employee->employee_email }}</td>
                    <td><strong>Designation:</strong> {{ $employee->designation }}</td>
                </tr>
                 <tr>
                    <td colspan="2"><strong>Assigned Site:</strong> {{ $assignedSite }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2 class="section-title">Salary Breakdown</h2>
            <table class="salary-table">
                <thead>
                    <tr>
                        <th>Earning Component</th>
                        <th class="text-right">Amount (INR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic Salary</td>
                        <td class="text-right">{{ number_format($basicSalary, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Approx Daily Rate (30 days)</td>
                        <td class="text-right">{{ number_format($dailyRate, 2) }}</td>
                    </tr>
                    <tr class="net-row">
                        <td>Net Salary</td>
                        <td class="text-right">{{ number_format($basicSalary, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="footer">
            This is a computer-generated salary slip and does not require a signature.
        </div>
    </div>
</body>
</html>
