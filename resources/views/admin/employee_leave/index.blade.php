@extends('layouts.app')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            @include('common.alert')


    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Employee Leave Requests</h4>

        <div class="btn-group">
            <a class="btn btn-outline-primary {{ $status=='pending'?'active':'' }}"
               href="{{ route('admin.employee_leave.index', ['status'=>'pending']) }}">
                Pending ({{ $counts['pending'] }})
            </a>
            <a class="btn btn-outline-success {{ $status=='accepted'?'active':'' }}"
               href="{{ route('admin.employee_leave.index', ['status'=>'accepted']) }}">
                Accepted ({{ $counts['accepted'] }})
            </a>
            <a class="btn btn-outline-danger {{ $status=='reject'?'active':'' }}"
               href="{{ route('admin.employee_leave.index', ['status'=>'reject']) }}">
                Rejected ({{ $counts['reject'] }})
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-body table-responsive">

            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Leave Date</th>
                        <th>Type</th>
                        <th>Comment</th>
                        <th>Status</th>
                        <th>Reason (if rejected)</th>
                        <th style="width:220px;">Action</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($leaves as $i => $r)
                    <tr>
                        <td>{{ $i+1 }}</td>
                        <td>{{ $r->employee_id }}</td>
                        <td>{{ \Carbon\Carbon::parse($r->leave_date)->format('d-m-Y') }}</td>
                        <td>
                            {{ $r->leave_type === 'F' ? 'Full Day' : 'Half Day' }}
                        </td>
                        <td style="max-width:280px; white-space:normal;">
                            {{ $r->comment }}
                        </td>
                        <td>
                            @if($r->status === 'pending')
                                <span class="badge bg-warning text-dark">Pending</span>
                            @elseif($r->status === 'accepted')
                                <span class="badge bg-success">Accepted</span>
                            @elseif($r->status === 'reject')
                                <span class="badge bg-danger">Rejected</span>
                            @else
                                <span class="badge bg-secondary">-</span>
                            @endif
                        </td>
                        <td style="max-width:240px; white-space:normal;">
                            {{ $r->reason }}
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.employee_leave.status') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="emp_leave_id" value="{{ $r->emp_leave_id }}">
                                <input type="hidden" name="status" value="accepted">
                                <button class="btn btn-sm btn-success"
                                        onclick="return confirm('Accept this leave?')">
                                    Accept
                                </button>
                            </form>

                            <button class="btn btn-sm btn-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rejectModal"
                                    onclick="openReject({{ $r->emp_leave_id }})">
                                Reject
                            </button>

                            <form method="POST" action="{{ route('admin.employee_leave.status') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="emp_leave_id" value="{{ $r->emp_leave_id }}">
                                <input type="hidden" name="status" value="pending">
                                <button class="btn btn-sm btn-secondary"
                                        onclick="return confirm('Set back to pending?')">
                                    Pending
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center">No records found</td></tr>
                @endforelse
                </tbody>
            </table>

        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('admin.employee_leave.status') }}" class="modal-content">
        @csrf
        <div class="modal-header">
            <h5 class="modal-title">Reject Leave</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <input type="hidden" name="emp_leave_id" id="reject_emp_leave_id">
            <input type="hidden" name="status" value="reject">

            <label class="form-label">Reason <span class="text-danger">*</span></label>
            <textarea name="reason" id="reject_reason" class="form-control" rows="4" required></textarea>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Reject</button>
        </div>
    </form>
  </div>
</div>
</div>
</div>

<script>
function openReject(id){
    document.getElementById('reject_emp_leave_id').value = id;
    document.getElementById('reject_reason').value = '';
}
</script>
@endsection