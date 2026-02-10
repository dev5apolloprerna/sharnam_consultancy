@extends('layouts.app')

@section('title', 'Construction Site List')

@section('content')

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
                          <div class="mt-1">
                            @foreach($site->assignedEmployees as $assign)
                                <span class="badge bg-info text-dark">{{ $assign->employee->employee_name ?? '' }}</span>
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
@endsection

@section('scripts')
{{-- IMPORTANT: csrf meta needed for AJAX --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
  // Setup CSRF for all jQuery AJAX calls
  $.ajaxSetup({
    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
  });

  // Check all
  $('#checkAll').on('change', function() {
    $('.record-checkbox').prop('checked', $(this).prop('checked'));
  });

  // If user unchecks any, uncheck "checkAll"
  $(document).on('change', '.record-checkbox', function() {
    if (!$(this).prop('checked')) $('#checkAll').prop('checked', false);
  });

  // SINGLE DELETE
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

  // BULK DELETE
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
  
   $(document).on('click', '.assignEmployeeBtn', function () {
        let site_id = $(this).data('id');
        let site_name = $(this).data('name');
        $('#assign_site_id').val(site_id);
        $('#assign_site_name').text(site_name);

        $.get(`../admin/construction-site/${site_id}/employees`, function (res) {
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
        $.post("../admin/construction-site/assign-employees", $(this).serialize(), function () {
            $('#assignModal').modal('hide');
            location.reload(); // refresh the page after success
        });
    });
</script>
@endsection
