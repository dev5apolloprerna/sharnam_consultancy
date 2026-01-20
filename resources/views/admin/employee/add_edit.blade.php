@extends('layouts.app')

@section('title', isset($employee) ? 'Edit Employee' : 'Add Employee')

@section('content')
@include('common.alert')
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

    <div class="card">
        <div class="card-header">
            <h4 class="card-title">{{ isset($employee) ? 'Edit' : 'Add' }} Employee</h4>
        </div>
        <div class="card-body">
            <form action="{{ isset($employee) ? route('admin.employee.update', $employee->employee_id) : route('admin.employee.store') }}" method="POST">
                @csrf
                @if(isset($employee)) @method('PUT') @endif

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Employee Name <span style="color:red;">*</span></label>
                        <input type="text" name="employee_name" class="form-control" value="{{ old('employee_name', $employee->employee_name ?? '') }}">
                        @if($errors->has('employee_name'))<span class="text-danger">{{ $errors->first('employee_name') }}</span>@endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone <span style="color:red;">*</span></label>
                        <input type="text" name="employee_phone" class="form-control" value="{{ old('employee_phone', $employee->employee_phone ?? '') }}">
                        @if($errors->has('employee_phone'))<span class="text-danger">{{ $errors->first('employee_phone') }}</span>@endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email <span style="color:red;">*</span></label>
                        <input type="email" name="employee_email" class="form-control" value="{{ old('employee_email', $employee->employee_email ?? '') }}">
                        @if($errors->has('employee_email'))<span class="text-danger">{{ $errors->first('employee_email') }}</span>@endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Address <span style="color:red;">*</span></label>
                        <input type="text" name="employee_address" class="form-control" value="{{ old('employee_address', $employee->employee_address ?? '') }}">
                        @if($errors->has('employee_address'))<span class="text-danger">{{ $errors->first('employee_address') }}</span>@endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Salary <span style="color:red;">*</span></label>
                        <input type="number" name="basic_salary" class="form-control" value="{{ old('basic_salary', $employee->basic_salary ?? '') }}">
                        @if($errors->has('basic_salary'))<span class="text-danger">{{ $errors->first('basic_salary') }}</span>@endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Designation <span style="color:red;">*</span></label>
                        <input type="text" name="designation" class="form-control" value="{{ old('designation', $employee->designation ?? '') }}">
                        @if($errors->has('designation'))<span class="text-danger">{{ $errors->first('designation') }}</span>@endif
                    </div>
                    @if(!isset($employee))
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password <span style="color:red;">*</span></label>
                        <input type="password" name="password" class="form-control" value="{{ old('password', $employee->password ?? '') }}">
                        @if($errors->has('password'))<span class="text-danger">{{ $errors->first('password') }}</span>@endif
                    </div>
                    @endif
                    <!-- <div class="col-md-6 mb-3">
                        <label class="form-label">Vehicle <span style="color:red;">*</span></label>
                        <select name="vehicle_id" class="form-control">
                            <option value="">Select Vehicle</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->vehicle_id }}" {{ (old('vehicle_id', $employee->vehicle_id ?? '') == $vehicle->vehicle_id) ? 'selected' : '' }}>
                                    {{ $vehicle->vehicle_name }} - {{ $vehicle->vehicle_no }}
                                </option>
                            @endforeach
                        </select>
                        @if($errors->has('vehicle_id'))<span class="text-danger">{{ $errors->first('vehicle_id') }}</span>@endif
                    </div> -->
                </div>
                <button type="submit" class="btn btn-primary">{{ isset($employee) ? 'Update' : 'Submit' }}</button>
                <a href="{{ route('admin.employee.index') }}" class="btn btn-secondary">Back</a>
            </form>
        </div>
    </div>
</div>
</div>
</div>

@endsection