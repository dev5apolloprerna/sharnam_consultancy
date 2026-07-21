@extends('layouts.app')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Employee Credit/Debit Ledger</h4>
        <a href="{{ route('admin.employee-credit.create') }}" class="btn btn-primary">+ Add Credit</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form class="card p-3 mb-3" method="GET" action="{{ route('admin.employee-credit.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Filter Employee</label>
                <select name="employee_id" class="form-control">
                    <option value="">All</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->employee_id }}" {{ (string)$qEmployee === (string)$emp->employee_id ? 'selected' : '' }}>
                            {{ $emp->employee_name }} ({{ $emp->member_id }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-dark w-100">Search</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Site</th>
                        <th>Credit</th>
                        <th>Debit</th>
                        <th>Running Balance</th>
                        <th>Accept/Reject Status</th>
                        <th>Action</th>
                        <th>Accepted By</th>
                        <th>Rejected By</th>
                        <!-- <th>Action Date</th> -->
                        <th>Reject Reason</th>
                        <th>Comment</th>
                        <th>Enter By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $i => $r)
                        @php
                            $creditAmount = (float) ($r->credit_balance ?? 0);
                            $debitAmount = (float) ($r->debit_balance ?? 0);
                            $runningBalance = (float) ($runningBalances[$r->ledger_id] ?? 0);
                            $approvalStatus = $r->status ?: 'accepted';
                            $approvalBy = $r->approvedBy?->employee_name ?? $r->approvedByUser?->full_name ?? '-';
                        @endphp
                        <tr>
                            <td>{{ $rows->firstItem() + $i }}</td>
                            <td>{{ date('d-m-Y',strtotime($r->date)) }}</td>
                            <td>{{ $r->employee?->employee_name }} ({{ $r->employee?->member_id }})</td>
                            <td>{{ $r->site_name ?: ($r->site?->site_name ?? $r->site_id ?? '-') }}</td>
                            <td>{{ number_format($creditAmount, 2) }}</td>
                            <td>{{ number_format($debitAmount, 2) }}</td>
                            <td>{{ number_format($runningBalance, 2) }}</td>
                            <td>
                                @if($approvalStatus === 'accepted')
                                    <span class="badge bg-success">Accepted</span>
                                @elseif($approvalStatus === 'reject')
                                    <span class="badge bg-danger">Rejected</span>
                                @else
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @endif
                            </td>
                              <td>
                                @if($debitAmount > 0)
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Expense approval actions">
                                        @if($approvalStatus !== 'accepted')
                                            <form method="POST" action="{{ route('admin.employee-credit.expense-status') }}">
                                                @csrf
                                                <input type="hidden" name="ledger_id" value="{{ $r->ledger_id }}">
                                                <input type="hidden" name="status" value="accepted">
                                                <button type="submit" class="btn btn-success" onclick="return confirm('Accept this expense?')">Accept</button>
                                            </form>
                                        @endif
                                        @if($approvalStatus !== 'reject')
                                            <form method="POST" action="{{ route('admin.employee-credit.expense-status') }}">
                                                @csrf
                                                <input type="hidden" name="ledger_id" value="{{ $r->ledger_id }}">
                                                <input type="hidden" name="status" value="reject">
                                                <input type="hidden" name="reason" value="">
                                                <button type="submit" class="btn btn-danger" onclick="return setExpenseRejectReason(this.form)">Reject</button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $approvalStatus === 'accepted' ? $approvalBy : '-' }}</td>
                            <td>{{ $approvalStatus === 'reject' ? $approvalBy : '-' }}</td>
                            <!-- <td>{{ $r->approved_at ? $r->approved_at->format('d-m-Y h:i A') : '-' }}</td> -->
                            <td>{{ $approvalStatus === 'reject' ? ($r->reason ?: '-') : '-' }}</td>

                            <td>{{ $r->comment }}</td>
                            <td>{{ $r->enteredBy?->full_name ?? $r->enteredByEmployee?->employee_name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="15" class="text-center">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">
            {{ $rows->links() }}
        </div>
    </div>
</div>
</div>
</div>
@endsection

@section('scripts')
<script>
    function setExpenseRejectReason(form) {
        var reason = window.prompt('Enter a rejection reason (optional):', '');

        if (reason === null) {
            return false;
        }

        form.querySelector('[name="reason"]').value = reason;
        return window.confirm('Reject this expense?');
    }
</script>
@endsection