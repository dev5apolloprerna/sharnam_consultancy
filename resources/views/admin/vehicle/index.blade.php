@extends('layouts.app')

@section('title', 'Vehicle Management')

@section('content')
@include('common.alert')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <!-- Left: Add New Vehicle -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">Add Vehicle</h5></div>
                        <div class="card-body">
                            <form action="{{ route('admin.vehicle.store') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Vehicle Name <span style="color:red;">*</span></label>
                                    <input type="text" name="vehicle_name" class="form-control" value="{{ old('vehicle_name') }}">
                                    @if($errors->has('vehicle_name'))<span class="text-danger">{{ $errors->first('vehicle_name') }}</span>@endif
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Vehicle No <span style="color:red;">*</span></label>
                                    <input type="text" name="vehicle_no" class="form-control" value="{{ old('vehicle_no') }}">
                                    @if($errors->has('vehicle_no'))<span class="text-danger">{{ $errors->first('vehicle_no') }}</span>@endif
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Assign To <span style="color:red;">*</span></label>
                                    <select name="employee_id" class="form-control">
                                        <option value="">Select Employee</option>
                                        @foreach($employees as $emp)
                                        <option value="{{ $emp->employee_id }}">{{ $emp->employee_name }}</option>
                                        @endforeach
                                    </select>
                                    @if($errors->has('employee_id'))<span class="text-danger">{{ $errors->first('employee_id') }}</span>@endif
                                </div>
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right: Vehicle List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Vehicle List</h5>
                            <form class="d-flex" method="GET">
                                <input type="text" name="vehicle_no" value="{{ request('vehicle_no') }}" placeholder="Search Vehicle No" class="form-control me-2">
                                <input type="text" name="employee_name" value="{{ request('employee_name') }}" placeholder="Search Employee Name" class="form-control me-2">

                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-primary">Submit</button>
                                    <a class="btn btn-light  me-2" href="{{ route('admin.vehicle.index') }}">Clear</a>
                                </div>
                      </form>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-danger mb-2" id="bulkDeleteBtn">Delete Selected</button>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="checkAll"></th>
                                        <th>Name</th>
                                        <th>No</th>
                                        <th>Employee</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($vehicles as $v)
                                    <tr>
                                        <td><input type="checkbox" class="record-checkbox" value="{{ $v->vehicle_id }}"></td>
                                        <td>{{ $v->vehicle_name }}</td>
                                        <td>{{ $v->vehicle_no }}</td>
                                        <td>{{ $v->employee->employee_name ?? '' }}</td>
                                        <td>{{ $v->iStatus ? 'Active' : 'Inactive' }}</td>
                                        <td>
                                            <a href="javascript:void(0);" class="text-primary editRecord" data-id="{{ $v->vehicle_id }}"><i class="fas fa-edit"></i></a>
                                            <a href="javascript:void(0);" class="text-danger deleteRecord" data-id="{{ $v->vehicle_id }}"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="d-flex justify-content-center">
                                {{ $vehicles->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" id="editForm">
                    @csrf
                    @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Edit Vehicle</h5></div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="edit_id">
                            <div class="mb-3">
                                <label class="form-label">Vehicle Name <span style="color:red;">*</span></label>
                                <input type="text" name="vehicle_name" id="edit_vehicle_name" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Vehicle No <span style="color:red;">*</span></label>
                                <input type="text" name="vehicle_no" id="edit_vehicle_no" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Assign To <span style="color:red;">*</span></label>
                                <select name="employee_id" id="edit_employee_id" class="form-control"></select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success">Update</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$('#checkAll').click(function () {
    $('.record-checkbox').prop('checked', this.checked);
});

$('#bulkDeleteBtn').click(function () {
    let ids = $('.record-checkbox:checked').map(function () {
        return $(this).val();
    }).get();
    if (ids.length > 0 && confirm('Delete selected vehicles?')) {
        $.post("{{ route('admin.vehicle.bulk-delete') }}", {
            _token: '{{ csrf_token() }}',
            ids: ids
        }, function () { location.reload(); });
    }
});

$('.deleteRecord').click(function () {
    let id = $(this).data('id');
    if (confirm('Delete this record?')) {
        $.ajax({
            url: `/admin/vehicle/${id}`,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: () => location.reload()
        });
    }
});

$('.editRecord').click(function () {
    let id = $(this).data('id');
    $.get(`/admin/vehicle/${id}/edit`, function (res) {
        $('#edit_id').val(res.vehicle.vehicle_id);
        $('#edit_vehicle_name').val(res.vehicle.vehicle_name);
        $('#edit_vehicle_no').val(res.vehicle.vehicle_no);
        let options = res.employees.map(e => `<option value="${e.employee_id}" ${res.vehicle.employee_id == e.employee_id ? 'selected' : ''}>${e.employee_name}</option>`);
        $('#edit_employee_id').html(options);
        $('#editForm').attr('action', `/admin/vehicle/${res.vehicle.vehicle_id}`);
        $('#editModal').modal('show');
    });
});
</script>
@endsection