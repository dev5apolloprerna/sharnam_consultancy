@extends('layouts.app')

@section('title', 'Assign Employee & Vehicle')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            @include('common.alert')

            {{-- Main Card --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Assign Employee & Vehicle</h5>
                    <a href="{{ url('/admin/construction-site') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>

                <div class="card-body">

                    {{-- Form Card --}}
                    <div class="card mb-3">
                        <div class="card-body">
                            <form id="assignForm" method="POST" action="{{ url('/admin/construction-site/employee-vehicle/save') }}">
                                @csrf
                                <input type="hidden" name="site_id" value="{{ $site->site_id }}">

                                <div class="row g-3 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                                        <select class="form-control" name="employee_id" required>
                                            <option value="">-- Select Employee --</option>
                                            @foreach($employees as $emp)
                                                <option value="{{ $emp->employee_id }}">{{ $emp->employee_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label d-block">Assign Vehicle?</label>

                                        {{-- Switch style (works with Bootstrap 5) --}}
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="assign_vehicle" name="assign_vehicle">
                                            <label class="form-check-label" for="assign_vehicle">Yes</label>
                                        </div>
                                    </div>

                                    <div class="col-md-3" id="vehicle_select_div" style="display: none;">
                                        <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                                        <select class="form-control" name="vehicle_id" id="vehicle_id">
                                            <option value="">-- Select Vehicle --</option>
                                            @foreach($vehicles as $veh)
                                                <option value="{{ $veh->vehicle_id }}">
                                                    {{ $veh->vehicle_name }} - {{ $veh->vehicle_no }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-1">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-plus me-1"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Assigned List Card --}}
                        <div class="card-header">
                            <h6 class="mb-0">Assigned List</h6>
                        </div>

                        <div class="card-body table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th style="width:60px;">#</th>
                                        <th>Employee</th>
                                        <th>Vehicle</th>
                                        <th style="width:120px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($assignments as $key => $item)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>

                                            <td>
                                                <span class="fw-semibold">{{ $item->employee_name ?? '-' }}</span>
                                            </td>

                                            <td>
                                                @if($item->vehicle_name)
                                                    {{ $item->vehicle_name }} - {{ $item->vehicle_no }}
                                                @else
                                                    <span class="badge bg-light text-muted">Not Assigned</span>
                                                @endif
                                            </td>

                                            <td>
                                                <form method="POST" action="{{ route('admin.construction-site.assignment.delete', $item->id) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Delete this record?')">
                                                        <i class="fas fa-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No assignments found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                </div> {{-- card-body --}}
            </div> {{-- card --}}

        </div>
    </div>
</div>

{{-- Keep your existing JS (no changes) --}}
<script>
    document.getElementById('assign_vehicle').addEventListener('change', function () {
        const vehicleDiv = document.getElementById('vehicle_select_div');
        if (this.checked) {
            vehicleDiv.style.display = 'block';
            document.getElementById('vehicle_id').setAttribute('required', 'required');
        } else {
            vehicleDiv.style.display = 'none';
            document.getElementById('vehicle_id').removeAttribute('required');
        }
    });
</script>
@endsection
