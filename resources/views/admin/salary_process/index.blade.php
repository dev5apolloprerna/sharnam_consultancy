@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Salary Process Filter</h5></div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.salary-process.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Month <span class="text-danger">*</span></label>
                        <select name="salary_month" class="form-select" required>
                            @for($month=1; $month<=12; $month++)
                                <option value="{{ $month }}" {{ (int)$selectedMonth === $month ? 'selected' : '' }}>{{ \Carbon\Carbon::createFromDate(null, $month, 1)->format('F') }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Year <span class="text-danger">*</span></label>
                        <select name="salary_year" class="form-select" required>
                            @for($year = now()->year + 1; $year >= now()->year - 5; $year--)
                                <option value="{{ $year }}" {{ (int)$selectedYear === $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-4"><button type="submit" class="btn btn-primary">Show Pending Salary List</button></div>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.salary-process.store') }}">
            @csrf
            <input type="hidden" name="salary_month" value="{{ $selectedMonth }}">
            <input type="hidden" name="salary_year" value="{{ $selectedYear }}">

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">Pending Salary Employee List ({{ \Carbon\Carbon::createFromDate(null, $selectedMonth, 1)->format('F') }} {{ $selectedYear }})</h5></div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered align-middle" id="pendingSalaryTable">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <th>Employee Name</th>
                                <th>Basic Salary</th>
                                <th>Leave Count (F/H)</th>
                                <th>Leave Detail</th>
                                <th>Deduction</th>
                                <th>Leave Deduction</th>
                                <th>Net Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingEmployees as $employee)
                                @php
                                    $leaveSummary = $leaveSummaries[$employee->employee_id] ?? [
                                        'full_day' => 0,
                                        'half_day' => 0,
                                        'total_units' => 0,
                                        'free_units' => 2,
                                        'chargeable_units' => 0,
                                        'per_day_salary' => 0,
                                        'leave_deduction' => 0,
                                    ];
                                    $deduct = (float) old('deductions.' . $employee->employee_id, 200);
                                    $amount = (float) $employee->basic_salary;
                                    $leaveDeduct = (float) old('leave_deductions.' . $employee->employee_id, $leaveSummary['leave_deduction']);
                                    $net = max(0, $amount - $deduct - $leaveDeduct);
                                @endphp
                                <tr>
                                    <td><input type="checkbox" class="form-check-input" name="selected_employee_ids[]" value="{{ $employee->employee_id }}" {{ in_array($employee->employee_id, old('selected_employee_ids', [])) ? 'checked' : '' }}></td>
                                    <td>{{ $employee->employee_name }}</td>
                                    <td><span class="salary-amount" data-employee-id="{{ $employee->employee_id }}">{{ number_format($amount, 2) }}</span></td>
                                    <td>
                                        <span class="badge bg-primary">F: {{ $leaveSummary['full_day'] }}</span>
                                        <span class="badge bg-info text-dark">H: {{ $leaveSummary['half_day'] }}</span>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary leave-detail-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#leaveDetailModal"
                                            data-employee-name="{{ $employee->employee_name }}"
                                            data-month="{{ \Carbon\Carbon::createFromDate(null, $selectedMonth, 1)->format('F') }}"
                                            data-year="{{ $selectedYear }}"
                                            data-full-day="{{ $leaveSummary['full_day'] }}"
                                            data-half-day="{{ $leaveSummary['half_day'] }}"
                                            data-total-leave="{{ number_format($leaveSummary['total_units'], 1, '.', '') }}"
                                            data-free-leave="{{ number_format($leaveSummary['free_units'], 1, '.', '') }}"
                                            data-extra-leave="{{ number_format($leaveSummary['chargeable_units'], 1, '.', '') }}"
                                            data-per-day-salary="{{ number_format($leaveSummary['per_day_salary'], 2, '.', '') }}"
                                            data-leave-deduction="{{ number_format($leaveSummary['leave_deduction'], 2, '.', '') }}"
                                        >View</button>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" class="form-control deduction-input" name="deductions[{{ $employee->employee_id }}]" data-employee-id="{{ $employee->employee_id }}" value="{{ $deduct }}">
                                        @error('deductions.' . $employee->employee_id)<span class="text-danger">{{ $message }}</span>@enderror
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" class="form-control leave-deduction-input" name="leave_deductions[{{ $employee->employee_id }}]" data-employee-id="{{ $employee->employee_id }}" value="{{ $leaveDeduct }}">
                                        @error('leave_deductions.' . $employee->employee_id)<span class="text-danger">{{ $message }}</span>@enderror
                                    </td>
                                    <td><strong class="net-text" id="net_{{ $employee->employee_id }}">{{ number_format($net, 2) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center">No pending employees for selected month and year.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @error('selected_employee_ids')<span class="text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="card-footer">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Paid Date <span class="text-danger">*</span></label>
                            <input type="date" name="paid_date" class="form-control" value="{{ old('paid_date', now()->toDateString()) }}" required>
                            @error('paid_date')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-4"><button type="submit" class="btn btn-success">Submit Selected Salaries</button></div>
                    </div>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Paid Salary Employee List ({{ \Carbon\Carbon::createFromDate(null, $selectedMonth, 1)->format('F') }} {{ $selectedYear }})</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr><th>#</th><th>Employee Name</th><th>Amount</th><th>Paid Date</th><th>Salary Slip</th></tr>
                    </thead>
                    <tbody>
                        @forelse($paidSalaryRows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row->employee->employee_name ?? 'N/A' }}</td>
                                <td>{{ number_format($row->paid_amount, 2) }}</td>
                                <td>{{ $row->paid_date ? \Carbon\Carbon::parse($row->paid_date)->format('d-m-Y') : '-' }}</td>
                                <td><a href="{{ route('admin.salary-process.slip', $row->id) }}" class="btn btn-sm btn-outline-primary">PDF</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center">No paid salary records found for selected period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal fade" id="leaveDetailModal" tabindex="-1" aria-labelledby="leaveDetailModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="leaveDetailModalLabel">Leave Calculation Detail</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-sm table-bordered mb-0">
                            <tbody>
                                <tr><th>Employee</th><td id="modalEmployeeName">-</td></tr>
                                <tr><th>Salary Period</th><td id="modalSalaryPeriod">-</td></tr>
                                <tr><th>Full Day Leave</th><td id="modalFullDayLeave">-</td></tr>
                                <tr><th>Half Day Leave</th><td id="modalHalfDayLeave">-</td></tr>
                                <tr><th>Total Leave Units</th><td id="modalTotalLeaveUnits">-</td></tr>
                                <tr><th>Allowed Free Leave</th><td id="modalFreeLeaveUnits">-</td></tr>
                                <tr><th>Chargeable Leave</th><td id="modalExtraLeaveUnits">-</td></tr>
                                <tr><th>Per Day Salary</th><td id="modalPerDaySalary">-</td></tr>
                                <tr><th>Leave Deduction</th><td id="modalLeaveDeduction">-</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    function getSalary(employeeId) {
        const salaryText = document.querySelector('.salary-amount[data-employee-id="' + employeeId + '"]')?.textContent || '0';
        return parseFloat(salaryText.replace(/,/g, '')) || 0;
    }

    function updateNet(employeeId) {
        const salary = getSalary(employeeId);
        const deduction = parseFloat(document.querySelector('.deduction-input[data-employee-id="' + employeeId + '"]')?.value || 0) || 0;
        const leaveDeduction = parseFloat(document.querySelector('.leave-deduction-input[data-employee-id="' + employeeId + '"]')?.value || 0) || 0;
        const net = Math.max(0, salary - deduction - leaveDeduction);
        const netText = document.getElementById('net_' + employeeId);
        if (netText) {
            netText.textContent = net.toFixed(2);
        }
    }

    document.querySelectorAll('.deduction-input, .leave-deduction-input').forEach(function (input) {
        const employeeId = input.dataset.employeeId;
        updateNet(employeeId);
        input.addEventListener('input', function () { updateNet(employeeId); });
    });

    document.querySelectorAll('.leave-detail-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('modalEmployeeName').textContent = this.dataset.employeeName || '-';
            document.getElementById('modalSalaryPeriod').textContent = (this.dataset.month || '-') + ' ' + (this.dataset.year || '-');
            document.getElementById('modalFullDayLeave').textContent = this.dataset.fullDay || '0';
            document.getElementById('modalHalfDayLeave').textContent = this.dataset.halfDay || '0';
            document.getElementById('modalTotalLeaveUnits').textContent = this.dataset.totalLeave || '0';
            document.getElementById('modalFreeLeaveUnits').textContent = this.dataset.freeLeave || '2';
            document.getElementById('modalExtraLeaveUnits').textContent = this.dataset.extraLeave || '0';
            document.getElementById('modalPerDaySalary').textContent = (parseFloat(this.dataset.perDaySalary || 0)).toFixed(2);
            document.getElementById('modalLeaveDeduction').textContent = (parseFloat(this.dataset.leaveDeduction || 0)).toFixed(2);
        });
    });
})();
</script>
@endsection