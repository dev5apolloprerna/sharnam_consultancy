@extends('layouts.app')

@section('title', 'Employee List')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
          <div class="col-lg-12">
            <div class="card">
                <div class="card-header" style="display: flex;justify-content: space-between;">
                    <h5 class="card-title mb-0">Employee List</h5>
                    <a class="btn btn-primary" href="{{ route('admin.employee.create') }}">Add Employee</a>
                </div>
@include('common.alert')

                <div class="card-body">
                    <div class="col-md-12 mt-3">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                             <form action="{{ route('admin.employee.search') }}" method="POST" class="d-flex">
                         @csrf
                                <input type="text" name="employee_name" value="{{ $employee ?? '' }}" placeholder="Search Name" class="form-control me-2">
                                <input type="text" name="employee_phone" value="{{ $phone ?? '' }}" placeholder="Search Phone" class="form-control me-2">
                                <input type="text" name="employee_email" value="{{ $email ?? '' }}" placeholder="Search Email" class="form-control me-2">
                                <button class="btn btn-primary">Search</button>
                                <a class="btn btn-light  me-2" href="{{ route('admin.employee.index') }}">Clear</a>
                            </form>
                        </div>
                    </div>
                </div>

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
                                        <a href="javascript:void(0);" class="text-warning changePasswordBtn" data-id="{{ $employee->employee_id }}"><i class="fas fa-key"></i></a>
                                        <!-- <a href="javascript:void(0);" class="text-success vehicleInfoBtn" data-id="{{ $employee->employee_id }}"><i class="fas fa-car"></i></a> -->
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
<!-- Vehicle Info Modal -->
<div class="modal fade" id="vehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="vehicleForm">
            @csrf
            <input type="hidden" name="employee_id" id="vehicle_employee_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vehicle Details</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vehicle Name <span style="color:red;">*</span></label>
                        <input type="text" name="vehicle_name" id="vehicle_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle No <span style="color:red;">*</span></label>
                        <input type="text" name="vehicle_no" id="vehicle_no" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Change Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="passwordForm">
            @csrf
            <input type="hidden" name="employee_id" id="password_employee_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">New Password <span style="color:red;">*</span></label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password <span style="color:red;">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
</div>
</div>
<script>
    $('.vehicleInfoBtn').click(function () {
        const id = $(this).data('id');
        $('#vehicle_employee_id').val(id);


        $.get(`/admin/employee/${id}/vehicle`, function (res) {
            $('#vehicle_name').val(res.vehicle?.vehicle_name || '');
            $('#vehicle_no').val(res.vehicle?.vehicle_no || '');
            $('#vehicleModal').modal('show');
        });
    });


    $('#vehicleForm').submit(function (e) {
        e.preventDefault();
        $.post("/admin/employee/vehicle/save", $(this).serialize(), function () {
            $('#vehicleModal').modal('hide');
            location.reload();
        });
    });

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

    $('.changePasswordBtn').click(function () {
        const id = $(this).data('id');
        $('#password_employee_id').val(id);
        $('#passwordModal').modal('show');
    });

    $('#passwordForm').submit(function (e) {
        e.preventDefault();
        $.post("../admin/employee/changepassword", $(this).serialize(), function () {
            $('#passwordModal').modal('hide');
            alert('Password updated successfully');
        });
    });
</script>
@endsection
