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

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Manual Leave Adjustment</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.employee-leave-ledger.manual-adjustment') }}" class="row g-3">
                            @csrf
                            <div class="col-md-12">
                                <label class="form-label">Employee <span class="text-danger">*</span></label>
                                <select name="employee_id" class="form-select" required>
                                    <option value="">Select Employee</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->employee_id }}" {{ (int) old('employee_id', $selectedEmployeeId) === (int) $employee->employee_id ? 'selected' : '' }}>
                                            {{ $employee->employee_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('employee_id')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Adjustment Type <span class="text-danger">*</span></label>
                                <select name="adjustment_type" class="form-select" required>
                                    <option value="credit" {{ old('adjustment_type') === 'credit' ? 'selected' : '' }}>Credit Leave</option>
                                    <option value="debit" {{ old('adjustment_type') === 'debit' ? 'selected' : '' }}>Debit Leave</option>
                                </select>
                                @error('adjustment_type')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                             <div class="col-md-6">
                                <label class="form-label">From Date</label>
                                <input type="date" name="from_date" id="manual_from_date" class="form-control" value="{{ old('from_date') }}">
                                @error('from_date')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">To Date</label>
                                <input type="date" name="to_date" id="manual_to_date" class="form-control" value="{{ old('to_date') }}">
                                <small class="text-muted">Use from/to dates for longer leave. Units are counted inclusively.</small>
                                @error('to_date')<span class="text-danger d-block">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Leave Units <span class="text-danger">*</span></label>
                                <input type="number" name="leave_units" id="manual_leave_units" class="form-control" min="0.5" step="0.5" value="{{ old('leave_units', 1) }}">
                                @error('leave_units')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                           
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Reason for manual leave adjustment">{{ old('description') }}</textarea>
                                @error('description')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Save Manual Adjustment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Monthly Leave Credit</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.employee-leave-ledger.monthly-credit') }}" class="row g-3">
                            @csrf
                            <div class="col-md-12">
                                <label class="form-label">Employee</label>
                                <select name="employee_id" class="form-select">
                                    <option value="">All Active Employees</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->employee_id }}" {{ (int) old('employee_id', $selectedEmployeeId) === (int) $employee->employee_id ? 'selected' : '' }}>
                                            {{ $employee->employee_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Leave empty to credit all active employees, same as cron job.</small>
                                @error('employee_id')<span class="text-danger d-block">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Month <span class="text-danger">*</span></label>
                                <select name="credit_month" class="form-select" required>
                                    @for($month = 1; $month <= 12; $month++)
                                        <option value="{{ $month }}" {{ (int) old('credit_month', now()->month) === $month ? 'selected' : '' }}>{{ \Carbon\Carbon::createFromDate(null, $month, 1)->format('F') }}</option>
                                    @endfor
                                </select>
                                @error('credit_month')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year <span class="text-danger">*</span></label>
                                <input type="number" name="credit_year" class="form-control" min="2000" max="2100" value="{{ old('credit_year', now()->year) }}" required>
                                @error('credit_year')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Credit Units <span class="text-danger">*</span></label>
                                <input type="number" name="credit_units" class="form-control" min="0.5" step="0.5" value="{{ old('credit_units', 2) }}" required>
                                @error('credit_units')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success">Run Monthly Credit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Leave Ledger History</h5>
                    @if($currentBalance !== null)
                        <small class="text-muted">Current Balance: {{ number_format($currentBalance, 2) }}</small>
                    @endif
                </div>
                <form method="GET" action="{{ route('admin.employee-leave-ledger.index') }}" class="d-flex gap-2">
                    <select name="employee_id" class="form-select">
                        <option value="">All Employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->employee_id }}" {{ (int) $selectedEmployeeId === (int) $employee->employee_id ? 'selected' : '' }}>
                                {{ $employee->employee_name }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                </form>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Opening</th>
                            <th>Credit</th>
                            <th>Debit</th>
                            <th>Closing</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledgerRows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row->employee->employee_name ?? '-' }}</td>
                                <td>
                                    @php
                                        $fromDate = $row->from_date ?: $row->transaction_date;
                                        $toDate = $row->to_date ?: $fromDate;
                                    @endphp
                                    @if($fromDate && $toDate && !\Carbon\Carbon::parse($fromDate)->isSameDay(\Carbon\Carbon::parse($toDate)))
                                        {{ \Carbon\Carbon::parse($fromDate)->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($toDate)->format('d-m-Y') }}
                                    @elseif($fromDate)
                                        {{ \Carbon\Carbon::parse($fromDate)->format('d-m-Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ ucwords(str_replace('_', ' ', $row->entry_type)) }}</td>
                                <td>{{ number_format((float) $row->opening_balance, 2) }}</td>
                                <td>{{ number_format((float) $row->credit_units, 2) }}</td>
                                <td>{{ number_format((float) $row->debit_units, 2) }}</td>
                                <td>{{ number_format((float) $row->closing_balance, 2) }}</td>
                                <td>{{ $row->description }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center">No leave ledger records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fromDateInput = document.getElementById('manual_from_date');
        const toDateInput = document.getElementById('manual_to_date');
        const leaveUnitsInput = document.getElementById('manual_leave_units');

        function updateLeaveUnitsFromDateRange() {
            if (!fromDateInput.value || !toDateInput.value) {
                return;
            }

            const fromDate = new Date(fromDateInput.value + 'T00:00:00');
            const toDate = new Date(toDateInput.value + 'T00:00:00');

            if (toDate < fromDate) {
                leaveUnitsInput.value = '';
                return;
            }

            const millisecondsPerDay = 24 * 60 * 60 * 1000;
            const leaveDays = Math.round((toDate - fromDate) / millisecondsPerDay) + 1;
            leaveUnitsInput.value = leaveDays;
        }

        fromDateInput.addEventListener('change', updateLeaveUnitsFromDateRange);
        toDateInput.addEventListener('change', updateLeaveUnitsFromDateRange);
        updateLeaveUnitsFromDateRange();
    });
</script>
@endsection