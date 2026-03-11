@extends('layouts.app')

@section('title', 'Construction Site List')

@section('content')
<style>
    #assignModal .modal-content {
        border-radius: 14px;
        overflow: hidden;
    }

    #assignModal .table th,
    #assignModal .table td {
        vertical-align: middle;
    }

    #assignModal .employee-name {
        font-weight: 500;
    }
</style>
<div class="main-content">
  <div class="page-content">
    <div class="container-fluid">

      <div class="col-lg-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Construction Site List</h5>
            <a class="btn btn-primary" href="{{ route('admin.construction-site.create') }}">Add Sites</a>
          </div>

@include('common.alert')

          <div class="card-body">

            {{-- Search Row --}}
                <div class="card-body">
                    <form action="{{ route('admin.construction-site.search') }}" method="POST">
                        @csrf
                        <div class="row align-items-end">
                            <div class="col-md-5">
                                <label for="site_name" class="form-label">Site Name</label>
                                <input type="text" name="site_name" id="site_name" class="form-control" value="{{ request('site_name') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="site_status_id" class="form-label">Site Status</label>
                                <select name="site_status_id" id="site_status_id" class="form-control">
                                    <option value="">-- Select Status --</option>
                                    @foreach($siteStatuses as $status)
                                        <option value="{{ $status->site_status_id }}" {{ request('site_status_id') == $status->site_status_id ? 'selected' : '' }}>
                                            {{ $status->site_status }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <button type="submit" class="btn btn-primary me-2">Search</button>
                                <a href="{{ route('admin.construction-site.index') }}" class="btn btn-light">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>


            {{-- Table Card like screenshot --}}
              <div class="card-body table-responsive">

                <button type="button" id="bulkDeleteBtn" class="btn btn-danger mb-2">
                  Delete Selected
                </button>

                <table class="table table-bordered align-middle mb-0">
                  <thead class="table-primary">
                    <tr>
                      <th style="width:40px;">
                        <input type="checkbox" id="checkAll">
                      </th>
                      <th>Site Name</th>
                      <th>Address</th>
                      <th>Pincode</th>
                      <th>Radius Distance</th>
                      <th>Site Status</th>
                      <th>Status</th>
                      <th style="width:120px;">Action</th>
                    </tr>
                  </thead>

                  <tbody>
                    @forelse($sites as $site)
                      <tr>
                        <td>
                          <input type="checkbox" class="record-checkbox" value="{{ $site->site_id }}">
                        </td>

                        <td>
                          {{ $site->site_name }}
                          <!--<div class="mt-1">
                            @foreach($site->assignedEmployees as $assign)
                                <span class="badge bg-info text-dark">{{ $assign->employee->employee_name ?? '' }}</span>
                            @endforeach
                          </div>-->
                          <div class="mt-1">
                            @foreach($site->assignedEmployees as $assign)
                                <span class="badge bg-info text-dark me-1 mb-1">
                                    {{ $assign->employee->employee_name ?? '' }}
                                    @if(!empty($assign->is_site_manager) && $assign->is_site_manager == 1)
                                        <span class="badge bg-success ms-1">Manager</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                        </td>

                        <td>{{ $site->site_address }}</td>
                        <td>{{ $site->site_pincode }}</td>
                        <td>{{ $site->site_radious_distance }}</td>
                        <td>{{ $site->siteStatus->site_status }}</td>

                        <td>
                          {{ $site->iStatus ? 'Active' : 'Inactive' }}
                        </td>

                        <td>
                          <a href="{{ route('admin.construction-site.edit', $site->site_id) }}"
                             class="text-primary me-2" title="Edit">
                            <i class="fas fa-edit"></i>
                          </a>

                          <a href="javascript:void(0);"
                             class="text-danger me-2 deleteRecord"
                             data-id="{{ $site->site_id }}"
                             title="Delete">
                            <i class="fas fa-trash"></i>
                          </a>

                          <a href="{{ url('/admin/construction-site/' . $site->site_id . '/employee-vehicle') }}"
                             class="text-success" title="Assign">
                            <i class="fas fa-users-cog"></i>
                          </a>
                          
                         <a href="javascript:void(0);" class="text-warning assignEmployeeBtn" data-id="{{ $site->site_id }}" data-name="{{ $site->site_name }}"><i class="fas fa-user-plus"></i></a>

                        </td>
                      </tr>
                    @empty
                      <tr>
                        <td colspan="7" class="text-center">No records found.</td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>

                <div class="d-flex justify-content-center mt-3">
                  {{ $sites->links() }}
                </div>

            </div>

          </div>
        </div>
      

<!-- Assign Modal -->
<!-- Assign Employee Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" id="assignForm">
            @csrf
            <input type="hidden" name="site_id" id="assign_site_id">

            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <div>
                        <h5 class="modal-title mb-0" id="assignModalLabel">
                            Assign Employees
                        </h5>
                        <small>Site: <strong id="assign_site_name"></strong></small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Search Employee</label>
                            <input type="text" id="employeeSearch" class="form-control" placeholder="Search employee by name">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" id="checkAllEmployees" class="btn btn-outline-primary me-2">Select All</button>
                            <button type="button" id="uncheckAllEmployees" class="btn btn-outline-secondary">Clear All</button>
                        </div>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;" class="text-center">Assign</th>
                                    <th>Employee Name</th>
                                    <th style="width: 180px;">Is Site Manager</th>
                                </tr>
                            </thead>
                            <tbody id="employeeAssignmentTable">
                                {{-- dynamic rows --}}
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        <small class="text-muted">
                            Note: Select employee first, then choose whether employee is site manager.
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Assignment</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
</div>
</div>
</div>
@endsection

@section('scripts')
{{-- IMPORTANT: csrf meta needed for AJAX --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // Check all sites
    $('#checkAll').on('change', function() {
        $('.record-checkbox').prop('checked', $(this).prop('checked'));
    });

    $(document).on('change', '.record-checkbox', function() {
        if (!$(this).prop('checked')) {
            $('#checkAll').prop('checked', false);
        }
    });

    // Delete single
    $(document).on('click', '.deleteRecord', function () {
        const id = $(this).data('id');

        if (!confirm('Are you sure you want to delete this site?')) return;

        $.ajax({
            url: `{{ url('/admin/construction-site') }}/${id}`,
            type: 'DELETE',
            success: function(res) {
                location.reload();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || 'Delete failed.');
            }
        });
    });

    // Bulk delete
    $('#bulkDeleteBtn').on('click', function() {
        const ids = $('.record-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            alert('Please select at least one record.');
            return;
        }

        if (!confirm(`Delete ${ids.length} selected site(s)?`)) return;

        $.ajax({
            url: `{{ route('admin.construction-site.bulk-delete') }}`,
            type: 'POST',
            data: { ids: ids },
            success: function(res) {
                location.reload();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || 'Bulk delete failed.');
            }
        });
    });

    // Render employee rows
    function renderEmployeeRows(employees, assignedData) {
        let assignedMap = {};

        assignedData.forEach(item => {
            assignedMap[item.employee_id] = item.is_site_manager;
        });

        let html = '';

        employees.forEach(emp => {
            const isChecked = assignedMap.hasOwnProperty(emp.employee_id);
            const managerValue = isChecked ? assignedMap[emp.employee_id] : 0;

            html += `
                <tr class="employee-row">
                    <td class="text-center">
                        <input type="checkbox"
                               class="form-check-input employee-checkbox"
                               name="employee_ids[]"
                               value="${emp.employee_id}"
                               ${isChecked ? 'checked' : ''}>
                    </td>
                    <td>
                        <span class="employee-name">${emp.employee_name}</span>
                    </td>
                    <td>
                        <select name="is_site_manager[${emp.employee_id}]"
                                class="form-select manager-select"
                                ${isChecked ? '' : 'disabled'}>
                            <option value="0" ${managerValue == 0 ? 'selected' : ''}>No</option>
                            <option value="1" ${managerValue == 1 ? 'selected' : ''}>Yes</option>
                        </select>
                    </td>
                </tr>
            `;
        });

        $('#employeeAssignmentTable').html(html);
    }

    // Open modal
    $(document).on('click', '.assignEmployeeBtn', function () {
        let site_id = $(this).data('id');
        let site_name = $(this).data('name');

        $('#assign_site_id').val(site_id);
        $('#assign_site_name').text(site_name);
        $('#employeeSearch').val('');
        $('#employeeAssignmentTable').html(`
            <tr>
                <td colspan="3" class="text-center py-4">Loading employees...</td>
            </tr>
        `);

        $.get(`{{ url('/admin/construction-site') }}/${site_id}/employees`, function (res) {
            renderEmployeeRows(res.employees, res.assigned);
            $('#assignModal').modal('show');
        }).fail(function () {
            alert('Failed to load employees.');
        });
    });

    // Enable/disable manager select based on checkbox
    $(document).on('change', '.employee-checkbox', function () {
        const row = $(this).closest('tr');
        const managerSelect = row.find('.manager-select');

        if ($(this).is(':checked')) {
            managerSelect.prop('disabled', false);
        } else {
            managerSelect.prop('disabled', true).val('0');
        }
    });

    // Search inside modal
    $(document).on('keyup', '#employeeSearch', function () {
        const keyword = $(this).val().toLowerCase().trim();

        $('#employeeAssignmentTable tr.employee-row').each(function () {
            const empName = $(this).find('.employee-name').text().toLowerCase();
            $(this).toggle(empName.indexOf(keyword) !== -1);
        });
    });

    // Select all
    $('#checkAllEmployees').on('click', function () {
        $('#employeeAssignmentTable .employee-checkbox').prop('checked', true).trigger('change');
    });

    // Clear all
    $('#uncheckAllEmployees').on('click', function () {
        $('#employeeAssignmentTable .employee-checkbox').prop('checked', false).trigger('change');
    });

    // Save assignment
    $('#assignForm').submit(function (e) {
        e.preventDefault();

        $.ajax({
            url: `{{ url('/admin/construction-site/assign-employees') }}`,
            type: 'POST',
            data: $(this).serialize(),
            success: function (res) {
                if (res.success) {
                    $('#assignModal').modal('hide');
                    location.reload();
                } else {
                    alert(res.message || 'Failed to save assignment.');
                }
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || 'Something went wrong.');
            }
        });
    });

</script>
@endsection
