@extends('layouts.app')

@section('title', 'Employee List')

@section('content')
@include('common.alert')
    <div class="main-content">
        <div class="page-content">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form class="d-flex" method="GET">
            <input type="text" name="employee_name" value="{{ request('employee_name') }}" placeholder="Search Name" class="form-control me-2">
            <input type="text" name="employee_phone" value="{{ request('employee_phone') }}" placeholder="Search Phone" class="form-control me-2">
            <input type="text" name="employee_email" value="{{ request('employee_email') }}" placeholder="Search Email" class="form-control me-2">
            <button class="btn btn-primary">Search</button>
        </form>
        <a href="{{ route('admin.employee.create') }}" class="btn btn-success">Add New</a>
    </div>
    <div class="card">
        <div class="card-body table-responsive">

    <form id="bulkDeleteForm">
        <button type="button" id="bulkDeleteBtn" class="btn btn-danger mb-2">Delete Selected</button>
                            <table class="table table-bordered align-middle">

            <thead>
                <tr>
                    <th><input type="checkbox" id="checkAll"></th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Designation</th>
                    <th>Salary</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                <tr>
                    <td><input type="checkbox" class="record-checkbox" value="{{ $employee->employee_id }}"></td>
                    <td>{{ $employee->employee_name }}</td>
                    <td>{{ $employee->employee_phone }}</td>
                    <td>{{ $employee->employee_email }}</td>
                    <td>{{ $employee->designation }}</td>
                    <td>{{ $employee->basic_salary }}</td>
                    <td>{{ $employee->vehicle->vehicle_name ?? '' }} {{ $employee->vehicle->vehicle_no ?? '' }}</td>
                    <td>{{ $employee->iStatus ? 'Active' : 'Inactive' }}</td>
                    <td>
                        <a href="{{ route('admin.employee.edit', $employee->employee_id) }}" class="text-primary me-2"><i class="fas fa-edit"></i></a>
                        <a href="javascript:void(0);" class="text-danger deleteRecord" data-id="{{ $employee->employee_id }}"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </form>
    <div class="d-flex justify-content-center">
        {{ $employees->links() }}
    </div>
</div>
</div>
</div>
</div>
</div>


<script>
    $('#checkAll').on('click', function () {
        $('.record-checkbox').prop('checked', $(this).prop('checked'));
    });
    $('#bulkDeleteBtn').click(function () {
        let ids = $('.record-checkbox:checked').map(function () {
            return $(this).val();
        }).get();
        if (ids.length > 0 && confirm('Are you sure to delete selected employees?')) {
            $.post("{{ route('admin.employee.bulk-delete') }}", {
                _token: '{{ csrf_token() }}',
                ids: ids
            }, function (res) {
                location.reload();
            });
        }
    });
    $('.deleteRecord').click(function () {
        let id = $(this).data('id');
        if (confirm('Are you sure to delete this record?')) {
            $.ajax({
                url: `/admin/employee/${id}`,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function () {
                    location.reload();
                }
            });
        }
    });
</script>
