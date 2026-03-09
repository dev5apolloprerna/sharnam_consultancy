@extends('layouts.app')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Employee Credit/Debit Ledger</h4>
        <a href="{{ route('admin.employee-credit.create') }}" class="btn btn-primary">+ Add Credit</a>
    </div>

    <form class="card p-3 mb-3" method="GET" action="{{ route('admin.employee-credit.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Filter Employee</label>
                <select name="employee_id" class="form-control">
                    <option value="">All</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->employee_id }}" {{ (string)$qEmployee === (string)$emp->employee_id ? 'selected' : '' }}>
                            {{ $emp->employee_name }} ({{ $emp->employee_id }})
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
                        <th>Debit</th>
                        <th>Running Credit Balance</th>
                        <th>Comment</th>
                        <th>Enter By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $i => $r)
                        <tr>
                            <td>{{ $rows->firstItem() + $i }}</td>
                            <td>{{ $r->date }}</td>
                            <td>{{ $r->employee?->employee_name }} ({{ $r->employee_id }})</td>
                            <td>{{ $r->site_id }}</td>
                            <td>{{ $r->debit_balance }}</td>
                            <td>{{ number_format((float)$r->credit_balance, 2) }}</td>
                            <td>{{ $r->comment }}</td>
                            <td>{{ $r->enter_by }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">No records found.</td></tr>
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