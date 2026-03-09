@extends('layouts.app')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
    <h4 class="mb-3">Give Credit to Employee</h4>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.employee-credit.store') }}" class="card p-3">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Employee</label>
                <select name="employee_id" class="form-control" required>
                    <option value="">-- Select Employee --</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->employee_id }}"
                            {{ old('employee_id') == $emp->employee_id ? 'selected' : '' }}>
                            {{ $emp->employee_name }} (ID: {{ $emp->employee_id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- <div class="col-md-3">
                <label class="form-label">Site ID</label>
                <input type="number" name="site_id" class="form-control" value="{{ old('site_id', 0) }}" min="0">
            </div> -->

            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Credit Amount</label>
                <input type="number" step="0.01" min="0.01" name="credit_amount" class="form-control"
                       value="{{ old('credit_amount') }}" required>
            </div>

            <div class="col-md-8">
                <label class="form-label">Comment</label>
                <input type="text" name="comment" class="form-control" value="{{ old('comment') }}" required>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save Credit</button>
                <a href="{{ route('admin.employee-credit.index') }}" class="btn btn-outline-secondary">View Ledger</a>
            </div>
        </div>
    </form>
</div>
</div>
</div>

@endsection