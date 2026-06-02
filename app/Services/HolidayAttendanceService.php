<?php

namespace App\Services;

use App\Models\EmployeeAttendance;
use App\Models\EmployeeMaster;
use App\Models\HolidayMaster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HolidayAttendanceService
{
    public function syncHoliday(HolidayMaster $holiday): int
    {
        $holidayDate = Carbon::parse($holiday->holiday_date)->toDateString();
        $employeeIds = EmployeeMaster::where('iStatus', 1)
            ->where('isDelete', 0)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $synced = 0;

        DB::transaction(function () use ($employeeIds, $holiday, $holidayDate, &$synced) {
            foreach ($employeeIds as $employeeId) {
                $attendance = EmployeeAttendance::where('employee_id', $employeeId)
                    ->whereDate('start_date_time', $holidayDate)
                    ->where('isDelete', 0)
                    ->first();

                $payload = [
                    'employee_id' => $employeeId,
                    'site_id' => 0,
                    'status' => 'L',
                    'start_date_time' => Carbon::parse($holidayDate)->startOfDay(),
                    'end_date_time' => Carbon::parse($holidayDate)->startOfDay(),
                    'comments' => 'Auto holiday leave: ' . $holiday->holiday_name,
                    'iStatus' => 1,
                    'isDelete' => 0,
                    'updated_at' => now(),
                ];

                if ($attendance) {
                    $attendance->update($payload);
                } else {
                    $payload['created_at'] = now();
                    EmployeeAttendance::create($payload);
                }

                $synced++;
            }
        });

        return $synced;
    }

    public function removeHolidayAttendance(string $holidayDate): int
    {
        return EmployeeAttendance::whereDate('start_date_time', Carbon::parse($holidayDate)->toDateString())
            ->where('status', 'L')
            ->where('comments', 'like', 'Auto holiday leave:%')
            ->where('isDelete', 0)
            ->delete();
    }
}
