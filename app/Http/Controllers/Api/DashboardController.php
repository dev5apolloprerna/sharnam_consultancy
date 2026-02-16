<?php
// app/Http/Controllers/Api/DashboardController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SiteAssignEmployee;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $employeeId = $request->user()->employee_id;

        if (empty($employeeId)) {
            return response()->json([
                'status' => false,
                'message' => 'Employee not mapped with this login.',
                'data' => []
            ], 422);
        }

        $sites = SiteAssignEmployee::query()
            ->where('site_assign_employees.site_emp_id', $employeeId)
            ->where('site_assign_employees.iStatus', 1)
            ->where('site_assign_employees.isDelete', 0)
            ->leftJoin('construction_site_master as s', 's.site_id', '=', 'site_assign_employees.site_id')
            ->select([
                'site_assign_employees.assign_id',
                'site_assign_employees.site_id',
                's.site_name',     // change if your site name column differs
                's.site_address',  // optional (remove if not exists)
            ])
            ->orderBy('site_assign_employees.assign_id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Logged-in employee assigned sites',
            'data' => [
                'employee_id' => $employeeId,
                'total_sites' => $sites->count(),
                'sites' => $sites,
            ]
        ]);
    }
}
/*
    public function dashboard(Request $request)
    {
        $employeeId = $request->query('employee_id'); // optional

        $q = SiteAssignEmployee::query()
            ->where('site_assign_employees.iStatus', 1)
            ->where('site_assign_employees.isDelete', 0)
            ->leftJoin('construction_site_master as s', 's.site_id', '=', 'site_assign_employees.site_id')
            ->leftJoin('employee_master as e', 'e.employee_id', '=', 'site_assign_employees.site_emp_id')
            ->select([
                'site_assign_employees.assign_id',
                'site_assign_employees.site_id',
                'site_assign_employees.site_emp_id',
                's.site_name',          // change if different
                's.site_address',       // optional; change if different
                'e.employee_name',      // change if different
                'e.employee_phone',    // optional; change if different
            ])
            ->orderBy('site_assign_employees.assign_id', 'desc');

        if (!empty($employeeId)) {
            $q->where('site_assign_employees.site_emp_id', $employeeId);
        }

        $rows = $q->get();

        // Group by employee for dashboard-style response
        $grouped = $rows->groupBy('site_emp_id')->map(function ($items) {
            $first = $items->first();

            return [
                'employee_id'   => $first->site_emp_id,
                'employee_name' => $first->employee_name ?? null,
                'employee_mobile' => $first->employee_phone ?? null,
                'sites' => $items->map(function ($r) {
                    return [
                        'assign_id'  => $r->assign_id,
                        'site_id'    => $r->site_id,
                        'site_name'  => $r->site_name ?? null,
                        'site_address' => $r->site_address ?? null,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard data',
            'data' => [
                'total_assignments' => $rows->count(),
                'employees' => $grouped,
            ]
        ]);
    }
}*/
