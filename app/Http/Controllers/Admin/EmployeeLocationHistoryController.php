<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLocationHistory;
use App\Models\EmployeeMaster;
use Illuminate\Http\Request;

class EmployeeLocationHistoryController extends Controller
{
    public function index(Request $request)
    {
        $employees = EmployeeMaster::where('isDelete', 0)
            ->orderBy('employee_name')
            ->get(['employee_id', 'employee_name']);

        $query = EmployeeLocationHistory::query()
            ->leftJoin('employee_master', 'employee_master.employee_id', '=', 'employee_location_history.employee_id')
            ->where('employee_location_history.isDelete', 0)
            ->select([
                'employee_location_history.*',
                'employee_master.employee_name',
                'employee_master.employee_phone',
            ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_location_history.employee_id', $request->employee_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('employee_location_history.created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('employee_location_history.created_at', '<=', $request->to_date);
        }

        $locations = $query->orderByDesc('employee_location_history.created_at')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.employee_location_history.index', [
            'locations' => $locations,
            'employees' => $employees,
            'employeeId' => $request->employee_id,
            'fromDate' => $request->from_date,
            'toDate' => $request->to_date,
        ]);
    }
}
