@extends('layouts.app')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            @include('common.alert')

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Holiday Master</h4>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control">
                        </div>
                        <div class="col-md-3 align-self-end">
                            <button class="btn btn-primary">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Add Holiday</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.holiday-master.store') }}" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Holiday Name</label>
                            <input type="text" name="holiday_name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Holiday Date</label>
                            <input type="date" name="holiday_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="col-md-1 align-self-end">
                            <button class="btn btn-success">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($holidays as $index => $holiday)
                            <tr>
                                <td>{{ $holidays->firstItem() + $index }}</td>
                                <td>{{ $holiday->holiday_name }}</td>
                                <td>{{ \Carbon\Carbon::parse($holiday->holiday_date)->format('d M Y') }}</td>
                                <td>{{ $holiday->description ?: '-' }}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal{{ $holiday->holiday_id }}">Edit</button>
                                    <form method="POST" action="{{ route('admin.holiday-master.delete', $holiday->holiday_id) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this holiday?')">Delete</button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editModal{{ $holiday->holiday_id }}" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog">
                                <form method="POST" action="{{ route('admin.holiday-master.update', $holiday->holiday_id) }}" class="modal-content">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Holiday</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Holiday Name</label>
                                            <input type="text" name="holiday_name" value="{{ $holiday->holiday_name }}" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Holiday Date</label>
                                            <input type="date" name="holiday_date" value="{{ $holiday->holiday_date }}" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <input type="text" name="description" value="{{ $holiday->description }}" class="form-control">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </div>
                                </form>
                              </div>
                            </div>
                        @empty
                            <tr><td colspan="5" class="text-center">No holidays found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>

                    {{ $holidays->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
