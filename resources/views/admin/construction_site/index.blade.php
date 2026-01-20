@extends('layouts.app')

@section('title', 'Construction Site List')

@section('content')
@include('common.alert')
<div class="main-content">
    <div class="page-content">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form class="d-flex" method="GET">
            <input type="text" name="site_name" value="{{ request('site_name') }}" placeholder="Search Site Name" class="form-control me-2">
            <select name="site_status_id" class="form-control me-2">
                <option value="">All Status</option>
                <option value="1" {{ request('site_status_id') == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('site_status_id') == 0 ? 'selected' : '' }}>Inactive</option>
            </select>
            <button class="btn btn-primary">Search</button>
        </form>
        <a href="{{ route('admin.construction-site.create') }}" class="btn btn-success">Add New</a>
    </div>
                <div class="card">
                        <div class="card-body table-responsive">

    <form id="bulkDeleteForm">
        <button type="button" id="bulkDeleteBtn" class="btn btn-danger mb-2">Delete Selected</button>
                            <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th><input type="checkbox" id="checkAll"></th>
                    <th>Site Name</th>
                    <th>Address</th>
                    <th>Pincode</th>
                    <th>Radius Distance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sites as $site)
                <tr>
                    <td><input type="checkbox" class="record-checkbox" value="{{ $site->site_id }}"></td>
                    <td>{{ $site->site_name }}
                        <div>
                            @foreach($site->assignedEmployees as $assign)
                                <span class="badge bg-info text-dark">{{ $assign->employee->employee_name ?? '' }}</span>
                            @endforeach
                        </div>
                    </td>
                    <td>{{ $site->site_address }}</td>
                    <td>{{ $site->site_pincode }}</td>
                    <td>{{ $site->{'site_radious_distance'} }}</td>
                    <td>{{ $site->iStatus ? 'Active' : 'Inactive' }}</td>
                    <td>
                        <a href="{{ route('admin.construction-site.edit', $site->site_id) }}" class="text-primary me-2"><i class="fas fa-edit"></i></a>
                        <a href="javascript:void(0);" class="text-danger deleteRecord" data-id="{{ $site->site_id }}"><i class="fas fa-trash"></i></a>
                        <a href="javascript:void(0);" class="text-warning assignEmployeeBtn" data-id="{{ $site->site_id }}" data-name="{{ $site->site_name }}"><i class="fas fa-user-plus"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </form>
    <div class="d-flex justify-content-center">
        {{ $sites->links() }}
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="assignForm">
            @csrf
            <input type="hidden" name="site_id" id="assign_site_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Employees to <span id="assign_site_name"></span></h5>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Employees <span style="color:red;">*</span></label>
                        <div id="employeeCheckboxes" class="row"></div>
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
</div>
</div>
</div>
</div>

@section('scripts')
<script>
    $(document).on('click', '.assignEmployeeBtn', function () {
        let site_id = $(this).data('id');
        let site_name = $(this).data('name');
        $('#assign_site_id').val(site_id);
        $('#assign_site_name').text(site_name);

        $.get(`/admin/construction-site/${site_id}/employees`, function (res) {
            let html = '';
            res.employees.forEach(emp => {
                let checked = res.assigned.includes(emp.employee_id) ? 'checked' : '';
                html += `<div class="col-md-6"><label><input type="checkbox" name="employee_ids[]" value="${emp.employee_id}" ${checked}> ${emp.employee_name}</label></div>`;
            });
            $('#employeeCheckboxes').html(html);
            $('#assignModal').modal('show');
        });
    });

    $('#assignForm').submit(function (e) {
        e.preventDefault();
        $.post("/admin/construction-site/assign-employees", $(this).serialize(), function () {
            $('#assignModal').modal('hide');
            location.reload(); // refresh the page after success
        });
    });
</script>
@endsection
