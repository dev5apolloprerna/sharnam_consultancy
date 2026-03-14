<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
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

        // Today range
        $todayStart = Carbon::now()->startOfDay();
        $todayEnd   = Carbon::now()->endOfDay();

        /**
         * Step 1: latest attendance row id per site for today
         */
        $latestIdPerSite = DB::table('employee_attendance as ea')
            ->where('ea.employee_id', $employeeId)
            ->where('ea.iStatus', 1)
            ->where('ea.isDelete', 0)
            ->whereBetween('ea.start_date_time', [$todayStart, $todayEnd])
            ->groupBy('ea.site_id')
            ->selectRaw('ea.site_id, MAX(ea.attendence_id) as latest_id');

        /**
         * Step 2: full latest attendance record per site
         */
        $latestAttendancePerSite = DB::table('employee_attendance as ea')
            ->joinSub($latestIdPerSite, 'mx', function ($join) {
                $join->on('mx.latest_id', '=', 'ea.attendence_id');
            })
            ->select([
                'ea.attendence_id',
                'ea.site_id',
                'ea.start_date_time',
                'ea.end_date_time',
            ]);

        /**
         * Assigned sites from site_assign_employees
         * Attendance data only for work status
         */
        $sites = SiteAssignEmployee::query()
            ->from('site_assign_employees')
            ->where('site_assign_employees.site_emp_id', $employeeId)
            ->where('site_assign_employees.iStatus', 1)
            ->where('site_assign_employees.isDelete', 0)
            ->leftJoin('construction_site_master as s', 's.site_id', '=', 'site_assign_employees.site_id')
            ->leftJoinSub($latestAttendancePerSite, 'la', function ($join) {
                $join->on('la.site_id', '=', 'site_assign_employees.site_id');
            })
            ->select([
                'site_assign_employees.assign_id',
                'site_assign_employees.site_emp_id',
                'site_assign_employees.site_id', // ✅ site_id from assignment table
                'site_assign_employees.iStatus',
                'site_assign_employees.isDelete',

                's.site_name',
                's.site_address',
                's.latitude',
                's.longitude',
                's.site_radious_distance',

                'la.attendence_id', // only attendance info from employee_attendance
                DB::raw('la.start_date_time as today_start_time'),
                DB::raw('la.end_date_time as today_end_time'),
                DB::raw("CASE 
                            WHEN la.start_date_time IS NOT NULL 
                                 AND la.end_date_time IS NULL 
                            THEN 1 
                            ELSE 0 
                         END as isWorkStart"),
            ])
            ->orderBy('site_assign_employees.assign_id', 'desc')
            ->get();

        /**
         * Overall latest attendance row today
         */
        $overallLatest = DB::table('employee_attendance')
            ->where('employee_id', $employeeId)
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->whereBetween('start_date_time', [$todayStart, $todayEnd])
            ->orderByDesc('attendence_id')
            ->first();

        $overall = [
            'isWorkStart'      => ($overallLatest && $overallLatest->start_date_time && empty($overallLatest->end_date_time)) ? 1 : 0,
            'today_start_time' => $overallLatest->start_date_time ?? null,
            'today_end_time'   => $overallLatest->end_date_time ?? null,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Logged-in employee assigned sites',
            'data' => [
                'employee_id' => $employeeId,
                'date' => $todayStart->toDateString(),
                'overall' => $overall,
                'total_sites' => $sites->count(),
                'sites' => $sites,
            ]
        ]);
    }
}