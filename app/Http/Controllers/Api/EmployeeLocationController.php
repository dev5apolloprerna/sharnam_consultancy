<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeLocationHistory;
use App\Models\EmployeeMaster;
use App\Models\ConstructionSiteMaster;
use App\Models\EmployeeAttendance;
use Carbon\Carbon;
/*use Illuminate\Support\Facades\Notification;
use App\Notifications\EmployeeLocationAlert;*/ // We'll create this

class EmployeeLocationController extends Controller
{

      public function trackLocation(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employee_master,employee_id',
            'latitude' => 'required|string',
            'longitude' => 'required|string',
            'address' => 'nullable|string',
            'comments' => 'nullable|string',
        ]);

        $now = now();
        $employeeId = (int) $request->employee_id;
        $latitude = (float) $request->latitude;
        $longitude = (float) $request->longitude;
        $isOutsideAssignedRadius = !$this->nearestAssignedSiteWithinRadius($employeeId, $latitude, $longitude);


        $attendanceAction = $this->syncAttendanceFromLocation(
            $employeeId,
            $latitude,
            $longitude,
            $request->address,
            $now
        );

        $historyComment = $this->locationHistoryComment($attendanceAction, $isOutsideAssignedRadius, $request->comments);

        if (!$historyComment) {
            return response()->json([
                'success' => true,
                'message' => 'Location is inside assigned radius; history was not stored.',
                'attendance' => $attendanceAction,
            ]);
        }


        $latestLocation = EmployeeLocationHistory::where('employee_id', $request->employee_id)
            ->where('isDelete', 0)
            ->orderByDesc('created_at')
            ->first();

        if ($latestLocation
            && (string) $latestLocation->latitude === (string) $request->latitude
            && (string) $latestLocation->longitude === (string) $request->longitude
            && (string) $latestLocation->comments === (string) $historyComment
            && Carbon::parse($latestLocation->created_at)->gt($now->copy()->subMinutes(1))) {
            return response()->json([
                'success' => true,
                'message' => 'Duplicate location ignored.',
                'attendance' => $attendanceAction,
            ]);
        }

        
        EmployeeLocationHistory::create([
            'employee_id' => $request->employee_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'comments' => $historyComment ?? $request->comments,
            'iStatus' => 1,
            'isDelete' => 0,
            'created_at' =>$now
        ]);

        //$this->sendLocationNotificationToAdmin($request->employee_id, $request->latitude, $request->longitude);

        return response()->json([
            'success' => true,
            'message' => 'Location stored successfully.',
            'attendance' => $attendanceAction,
        ]);
    }
    private function locationHistoryComment(array $attendanceAction, bool $isOutsideAssignedRadius, ?string $requestComment): ?string
    {
        if (($attendanceAction['action'] ?? null) === 'started') {
            return 'Start Day';
        }

        if (($attendanceAction['action'] ?? null) === 'ended') {
            return 'End Day';
        }

        if ($isOutsideAssignedRadius) {
            return $requestComment ?: 'Employee is outside assigned site radius.';
        }

        return null;
    }

    private function syncAttendanceFromLocation(int $employeeId, float $latitude, float $longitude, ?string $address, Carbon $now): array
    {
        if ($now->isSunday()) {
            return $this->syncSundayHolidayAttendance($employeeId, $now);
        }

        $siteMatch = $this->nearestAssignedSiteWithinRadius($employeeId, $latitude, $longitude);
        $openAttendance = $this->openAttendanceForToday($employeeId, $now);

        if ($openAttendance && (!$siteMatch || (int) $openAttendance->site_id === (int) $siteMatch['site']->site_id)) {
            return $this->closeAttendanceWhenShiftEnded($openAttendance, $latitude, $longitude, $address, $now);
        }

        if (!$siteMatch) {
            return [
                'action' => 'none',
                'message' => 'Employee is outside assigned site radius.',
                'isWorkStart' => $openAttendance ? 1 : 0,
            ];
        }

        if ($openAttendance) {
            return [
                'action' => 'none',
                'message' => 'Attendance already started for another site.',
                'attendance_id' => $openAttendance->attendence_id,
                'isWorkStart' => 1,
            ];
        }

        return $this->startAttendanceForCurrentShift($employeeId, $siteMatch['site'], $latitude, $longitude, $address, $now, $siteMatch['distance_meters']);
    }

    private function syncSundayHolidayAttendance(int $employeeId, Carbon $now): array
    {
        $holidayDate = $now->toDateString();
        $attendance = EmployeeAttendance::where('employee_id', $employeeId)
            ->whereDate('start_date_time', $holidayDate)
            ->where('isDelete', 0)
            ->first();

        if (!$attendance) {
            $attendance = EmployeeAttendance::create([
                'employee_id' => $employeeId,
                'site_id' => 0,
                'status' => 'L',
                'start_date_time' => $now->copy()->startOfDay(),
                'end_date_time' => $now->copy()->startOfDay(),
                'comments' => 'Auto Sunday holiday',
                'iStatus' => 1,
                'isDelete' => 0,
            ]);
        }

        return [
            'action' => 'holiday',
            'message' => 'Sunday is a weekly holiday. Attendance was not started.',
            'attendance_id' => $attendance->attendence_id,
            'isWorkStart' => 0,
        ];
    }

    private function startAttendanceForCurrentShift(int $employeeId, ConstructionSiteMaster $site, float $latitude, float $longitude, ?string $address, Carbon $now, float $distanceMeters): array
    {
        $shift = $this->shiftWindow($now);

        if (!$shift) {
            return [
                'action' => 'none',
                'message' => 'Location is inside site radius but outside attendance shift time.',
                'site_id' => $site->site_id,
                'distance_meters' => round($distanceMeters, 2),
                'isWorkStart' => 0,
            ];
        }

        $attendance = EmployeeAttendance::create([
            'employee_id' => $employeeId,
            'site_id' => $site->site_id,
            'status' => $shift['status'],
            'start_location' => $address,
            'start_date_time' => $shift['start_time'],
            'start_latitude' => $latitude,
            'start_longitude' => $longitude,
            'comments' => $shift['comment'],
            'iStatus' => 1,
            'isDelete' => 0,
        ]);

        return [
            'action' => 'started',
            'message' => 'Day started automatically from location.',
            'attendance_id' => $attendance->attendence_id,
            'site_id' => $site->site_id,
            'shift' => $shift['name'],
            'isWorkStart' => 1,
            'distance_meters' => round($distanceMeters, 2),
        ];
    }

    private function closeAttendanceWhenShiftEnded(EmployeeAttendance $attendance, float $latitude, float $longitude, ?string $address, Carbon $now): array
    {
        $start = Carbon::parse($attendance->start_date_time);
        $firstHalfEnd = $now->copy()->setTime(13, 30, 0);
        $fullDayEnd = $now->copy()->setTime(18, 30, 0);

        if ($now->lt($firstHalfEnd)) {
            return [
                'action' => 'none',
                'message' => 'Attendance is running for the current shift.',
                'attendance_id' => $attendance->attendence_id,
                'isWorkStart' => 1,
            ];
        }

        $endTime = $now;
        $status = 'H';
        $comment = 'Auto half day attendance from location tracking';

        if ($now->gte($fullDayEnd)) {
            $endTime = $fullDayEnd;
            $status = $start->lte($now->copy()->setTime(9, 30, 0)) ? 'P' : 'H';
            $comment = $status === 'P'
                ? 'Auto full day attendance from location tracking'
                : 'Auto half day attendance from location tracking';
        } elseif ($now->lt($now->copy()->setTime(14, 30, 0))) {
            $endTime = $firstHalfEnd;
        } else {
            return [
                'action' => 'none',
                'message' => 'Attendance is running until full day end time.',
                'attendance_id' => $attendance->attendence_id,
                'isWorkStart' => 1,
            ];
        }

        $attendance->update([
            'status' => $status,
            'end_location' => $address,
            'end_date_time' => $endTime,
            'end_latitude' => $latitude,
            'end_longitude' => $longitude,
            'comments' => $comment,
        ]);

        return [
            'action' => 'ended',
            'message' => 'Day ended automatically from location.',
            'attendance_id' => $attendance->attendence_id,
            'status' => $status,
            'isWorkStart' => 0,
        ];
    }
    private function shiftWindow(Carbon $now): ?array
    {
        $firstStart = $now->copy()->setTime(9, 30, 0);
        $firstEnd = $now->copy()->setTime(13, 30, 0);
        $secondStart = $now->copy()->setTime(14, 30, 0);
        $secondEnd = $now->copy()->setTime(18, 30, 0);

        if ($now->lte($firstEnd)) {
            return [
                'name' => 'first_half_or_full_day',
                'start_time' => $now->lt($firstStart) ? $firstStart : $now,
                'status' => 'P',
                'comment' => 'Auto attendance started by location tracking',
            ];
        }

        if ($now->gte($secondStart) && $now->lte($secondEnd)) {
            return [
                'name' => 'second_half',
                'start_time' => $now->lt($secondStart) ? $secondStart : $now,
                'status' => 'H',
                'comment' => 'Auto second half attendance started by location tracking',
            ];
        }

        return null;
    }

    private function openAttendanceForToday(int $employeeId, Carbon $now): ?EmployeeAttendance
    {
        return EmployeeAttendance::where('employee_id', $employeeId)
            ->whereDate('start_date_time', $now->toDateString())
            ->whereNotNull('start_date_time')
            ->whereNull('end_date_time')
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->orderByDesc('attendence_id')
            ->first();
    }

    private function nearestAssignedSiteWithinRadius(int $employeeId, float $latitude, float $longitude): ?array
    {
        $sites = ConstructionSiteMaster::query()
            ->join('site_assign_employees as sae', 'sae.site_id', '=', 'construction_site_master.site_id')
            ->where('sae.site_emp_id', $employeeId)
            ->where('sae.iStatus', 1)
            ->where('sae.isDelete', 0)
            ->where('construction_site_master.iStatus', 1)
            ->where('construction_site_master.isDelete', 0)
            ->select('construction_site_master.*')
            ->get();

        $nearest = null;

        foreach ($sites as $site) {
            if (!is_numeric($site->latitude) || !is_numeric($site->longitude) || !is_numeric($site->site_radious_distance)) {
                continue;
            }

            $distanceMeters = $this->distanceInMeters($latitude, $longitude, (float) $site->latitude, (float) $site->longitude);
            $radiusMeters = (float) $site->site_radious_distance;

            if ($distanceMeters <= $radiusMeters && (!$nearest || $distanceMeters < $nearest['distance_meters'])) {
                $nearest = [
                    'site' => $site,
                    'distance_meters' => $distanceMeters,
                ];
            }
        }

        return $nearest;
    }

    private function distanceInMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusMeters = 6371000;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) * sin($latitudeDelta / 2)
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude))
            * sin($longitudeDelta / 2) * sin($longitudeDelta / 2);

        return $earthRadiusMeters * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
    protected function sendLocationNotificationToAdmin($employee_id, $latitude, $longitude)
    {
        $employee = EmployeeMaster::find($employee_id);
        $adminFcmToken = 'admin_fcm_token_here'; // Replace or fetch dynamically

        $payload = [
            'to' => $adminFcmToken,
            'notification' => [
                'title' => 'Location Update',
                'body' => $employee->employee_name . ' is at [' . $latitude . ', ' . $longitude . ']',
            ],
            'data' => [
                'employee_id' => $employee_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
        ];

        $client = new \GuzzleHttp\Client();
        $client->post('https://fcm.googleapis.com/fcm/send', [
            'headers' => [
                'Authorization' => 'key=' . env('FCM_SERVER_KEY'),
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);
    }


}