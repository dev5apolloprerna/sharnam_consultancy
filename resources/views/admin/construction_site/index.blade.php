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
                            <td>{{ $site->site_name }}</td>
                            <td>{{ $site->site_address }}</td>
                            <td>{{ $site->site_pincode }}</td>
                            <td>{{ $site->{'site_radious_distance'} }}</td>
                            <td>{{ $site->iStatus ? 'Active' : 'Inactive' }}</td>
                            <td>
                                <a href="{{ route('admin.construction-site.edit', $site->site_id) }}" class="text-primary me-2"><i class="fas fa-edit"></i></a>
                                <a href="javascript:void(0);" class="text-danger deleteRecord" data-id="{{ $site->site_id }}"><i class="fas fa-trash"></i></a>
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
        if (ids.length > 0 && confirm('Are you sure to delete selected sites?')) {
            $.post("{{ route('admin.construction-site.bulk-delete') }}", {
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
                url: `/admin/construction-site/${id}`,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function () {
                    location.reload();
                }
            });
        }
    });
</script>
