<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveMaster;
use Illuminate\Http\Request;

class EmployeeLeaveController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending'); // pending default

        $query = EmployeeLeaveMaster::with('employee')
            ->where('isDelete', 0)
            ->where('iStatus', 1);

        if (in_array($status, ['pending','accepted','reject'], true)) {
            $query->where('status', $status);
        }

        $leaves = $query->orderBy('leave_date', 'desc')
            ->orderBy('emp_leave_id', 'desc')
            ->get();

        $counts = [
            'pending'  => EmployeeLeaveMaster::where('isDelete',0)->where('iStatus',1)->where('status','pending')->count(),
            'accepted' => EmployeeLeaveMaster::where('isDelete',0)->where('iStatus',1)->where('status','accepted')->count(),
            'reject'   => EmployeeLeaveMaster::where('isDelete',0)->where('iStatus',1)->where('status','reject')->count(),
        ];

        return view('admin.employee_leave.index', compact('leaves','status','counts'));
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'emp_leave_id' => 'required|integer',
            'status'       => 'required|in:accepted,reject,pending',
            'reason'       => 'nullable|string',
        ]);

        if ($request->status === 'reject' && !trim((string)$request->reason)) {
            return back()->with('error', 'Reject reason is required.');
        }

        $leave = EmployeeLeaveMaster::where('emp_leave_id', (int)$request->emp_leave_id)
            ->where('isDelete', 0)
            ->firstOrFail();

        $leave->status = $request->status;

        // if rejected save reason else clear it (optional behavior)
        if ($request->status === 'reject') {
            $leave->reason = $request->reason;
        } else {
            $leave->reason = '';
        }

        $leave->updated_at = now();
        $leave->save();

        return back()->with('success', 'Leave status updated successfully.');
    }
}