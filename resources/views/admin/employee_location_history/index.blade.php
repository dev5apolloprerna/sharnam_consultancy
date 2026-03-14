@extends('layouts.app')
@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Employee Location History</h4>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.employee-location-history.index') }}" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Employee</label>
                            <select name="employee_id" class="form-control">
                                <option value="">All Employees</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->employee_id }}" {{ (string) $employeeId === (string) $employee->employee_id ? 'selected' : '' }}>
                                        {{ $employee->employee_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" value="{{ $fromDate }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" value="{{ $toDate }}" class="form-control">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Employee</th>
                                    <th>Phone</th>
                                    <th>Latitude</th>
                                    <th>Longitude</th>
                                    <th>Address</th>
                                    <th>Comments</th>
                                    <th>Tracked At</th>
                                    <th>Map</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($locations as $index => $location)
                                    <tr>
                                        <td>{{ $locations->firstItem() + $index }}</td>
                                        <td>{{ $location->employee_name ?? 'N/A' }}</td>
                                        <td>{{ $location->employee_phone ?? 'N/A' }}</td>
                                        <td>{{ $location->latitude }}</td>
                                        <td>{{ $location->longitude }}</td>
                                        <td>{{ $location->address ?: 'N/A' }}</td>
                                        <td>{{ $location->comments ?: 'N/A' }}</td>
                                        <td>{{ optional($location->created_at)->format('d M Y, h:i A') }}</td>
                                        <td>
                                            @if ($location->latitude && $location->longitude)
                                                <a href="https://maps.google.com/?q={{ $location->latitude }},{{ $location->longitude }}" target="_blank" class="btn btn-sm btn-outline-primary">Open</a>
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">No location history found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $locations->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
