<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Illuminate\Http\Request;
use App\Models\EmployeeMaster;
use App\Models\EmployeeAttendance;
use App\Models\HolidayMaster;
use App\Models\VehicleMaster;
use App\Models\SiteAssignEmployee;

class EmployeeAuthController extends Controller
{
   public function login(Request $request)
    {
        $rules = [
            'employee_phone' => 'required|digits:10',
            'password' => 'required|string',
            'device_token' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $employee = EmployeeMaster::where('employee_phone', $request->employee_phone)
            ->where('isDelete', 0)
            ->first();

        
        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return response()->json(['status' => false, 'message' => 'Invalid login credentials'], 401);
        }
        
         if (!empty($employee->last_working_date) && now()->toDateString() > $employee->last_working_date) {
            if ((int) $employee->iStatus === 1) {
                $employee->iStatus = 0;
                $employee->save();
            }
        }

        if ((int) $employee->iStatus !== 1) {
            return response()->json(['status' => false, 'message' => 'Your account is inactive. Please contact admin.'], 403);
        }

        if($employee){

            if($employee->is_resigned == 1 && now()->gt($employee->last_working_date)){
                return response()->json([
                    'status' => false,
                    'message' => 'Your account is inactive. Please contact admin.'
                ]);
            }

        }

        $attendance = EmployeeAttendance::where('employee_id', $employee->employee_id)
        ->whereDate('start_date_time', now()->toDateString())
        ->whereNotNull('start_date_time')
        ->first();

        $isWorkStart = $attendance ? 1 : 0;
        
        $attendance1 = EmployeeAttendance::where('employee_id', $employee->employee_id)
        ->whereDate('end_date_time', now()->toDateString())
        ->whereNotNull('end_date_time')
        ->first();

        $isWorkEnd = $attendance1 ? 1 : 0;

        if ($request->filled('device_token') && $employee->device_token !== $request->device_token) {
            $employee->device_token = $request->device_token;
            $employee->save();
        }



        $token = JWTAuth::fromUser($employee);
        $holidays = $this->upcomingHolidays();
        $roleFlags = $this->employeeRoleFlags($employee->employee_id);

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'isWorkStart' => $isWorkStart,
            'isWorkEnd' => $isWorkEnd,
            'customer' => $employee,
            'is_employee' => $roleFlags['is_employee'],
            'is_manager' => $roleFlags['is_manager'],
            /*'holidays' => $holidays,
            'holiday_count' => count($holidays),*/
             'profile_image_url' => !empty($employee->profile_image)
                ? asset('/profile/' . $employee->profile_image)
                : null
        ]);
    }
    public function updateDeviceToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer',
            'device_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $employee = EmployeeMaster::where('employee_id', $request->employee_id)
            ->where('isDelete', 0)
            ->first();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $updated = false;

        if ($employee->device_token !== $request->device_token) {
            $employee->device_token = $request->device_token;
            $employee->save();
            $updated = true;
        }

        return response()->json([
            'status' => true,
            'message' => $updated ? 'Device token updated successfully' : 'Device token already up to date',
            'employee_id' => $employee->employee_id,
            'device_token' => $employee->device_token,
        ]);
    }

    public function assignedVehicleList(Request $request)
    {
        $employee = auth()->guard('api')->user();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorised'
            ], 401);
        }

        $assignedVehicles = $this->assignedVehicles($employee->employee_id);
        $siteAssignedVehicles = $this->siteAssignedVehicles($employee->employee_id);
        $assignedVehicle = $assignedVehicles[0] ?? $this->firstSiteAssignedVehicle($siteAssignedVehicles);

        return response()->json([
            'status' => true,
            'message' => 'Assigned vehicle list fetched successfully',
            'data' => [
                'employee_id' => $employee->employee_id,
                'assigned_vehicle' => $assignedVehicle,
                'assigned_vehicles' => $assignedVehicles,
                'site_assigned_vehicles' => $siteAssignedVehicles,
            ],
        ]);
    }

    private function assignedVehicles(int $employeeId): array
    {
        return VehicleMaster::where('employee_id', $employeeId)
            ->where('isDelete', 0)
            ->where('iStatus', 1)
            ->orderBy('vehicle_id', 'desc')
            ->get(['vehicle_id', 'vehicle_name', 'vehicle_no', 'employee_id'])
            ->map(function ($vehicle) {
                return [
                    'vehicle_id' => $vehicle->vehicle_id,
                    'vehicle_name' => $vehicle->vehicle_name,
                    'vehicle_no' => $vehicle->vehicle_no,
                    'employee_id' => $vehicle->employee_id,
                ];
            })
            ->values()
            ->toArray();
    }

    private function siteAssignedVehicles(int $employeeId): array
    {
        return DB::table('construction_employee_vehicle as sev')
            ->leftJoin('vehicle_master as v', 'v.vehicle_id', '=', 'sev.vehicle_id')
            ->leftJoin('construction_site_master as s', 's.site_id', '=', 'sev.construction_id')
            ->where('sev.employee_id', $employeeId)
            ->where('sev.isDelete', 0)
            ->where('sev.iStatus', 1)
            ->where(function ($query) {
                $query->whereNull('sev.vehicle_id')
                    ->orWhere(function ($vehicleQuery) {
                        $vehicleQuery->where('v.isDelete', 0)
                            ->where('v.iStatus', 1);
                    });
            })
            ->orderByDesc('sev.id')
            ->get([
                'sev.id as assignment_id',
                'sev.construction_id as site_id',
                's.site_name',
                'sev.employee_id',
                'sev.vehicle_id',
                'v.vehicle_name',
                'v.vehicle_no',
            ])
            ->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->assignment_id,
                    'site_id' => $assignment->site_id,
                    'site_name' => $assignment->site_name,
                    'employee_id' => $assignment->employee_id,
                    'vehicle' => $assignment->vehicle_id ? [
                        'vehicle_id' => $assignment->vehicle_id,
                        'vehicle_name' => $assignment->vehicle_name,
                        'vehicle_no' => $assignment->vehicle_no,
                    ] : null,
                ];
            })
            ->values()
            ->toArray();
    }

    private function firstSiteAssignedVehicle(array $siteAssignedVehicles): ?array
    {
        foreach ($siteAssignedVehicles as $assignment) {
            if (!empty($assignment['vehicle'])) {
                return $assignment['vehicle'];
            }
        }

        return null;
    }

    private function upcomingHolidays(): array
    {
        return HolidayMaster::where('isDelete', 0)
            ->where('iStatus', 1)
            ->whereDate('holiday_date', '>=', now()->toDateString())
            ->orderBy('holiday_date', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($holiday) {
                $holidayDate = Carbon::parse($holiday->holiday_date);

                return [
                    'holiday_id' => $holiday->holiday_id,
                    'holiday_name' => $holiday->holiday_name,
                    'holiday_date' => $holidayDate->toDateString(),
                    'holiday_day' => $holidayDate->format('l'),
                    'description' => $holiday->description,
                ];
            })
            ->values()
            ->toArray();
    }
     private function employeeRoleFlags(int $employeeId): array
    {
        $isManager = SiteAssignEmployee::where('site_emp_id', $employeeId)
            ->where('is_site_manager', 1)
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->exists();

        return [
            'is_employee' => $isManager ? 0 : 1,
            'is_manager' => $isManager ? 1 : 0,
        ];
    }
    public function profile(Request $request)
    {
        $employee = auth()->guard('api')->user();
    
        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorised'
            ], 401);
        }
        $roleFlags = $this->employeeRoleFlags($employee->employee_id);

        return response()->json([
            'status' => true,
            'message' => 'Employee profile fetched successfully',
            'data' => [
                'employee_id'     => $employee->employee_id,
                'employee_name'   => $employee->employee_name,
                'employee_phone' => $employee->employee_phone,
                'employee_email'  => $employee->employee_email,
                                'is_employee'    => $roleFlags['is_employee'],
                'is_manager'     => $roleFlags['is_manager'],
                 'profile_image_url' => !empty($employee->profile_image)
                ? asset('/profile/' . $employee->profile_image)
                : null,
                'created_at'      => $employee->created_at,
            ]
        ]);
    }
    public function updateProfile(Request $request)
    {
        $employee = auth()->guard('api')->user();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorised'
            ], 401);
        }

        $request->validate([
            'employee_name'   => 'required|string|max:255',
            'employee_phone' => 'required|digits:10|unique:employee_master,employee_phone,' . $employee->employee_id . ',employee_id',
            'employee_email'  => 'required|email|unique:employee_master,employee_email,' . $employee->employee_id . ',employee_id',

            // ✅ image validation
            'profile_image'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $employee->employee_name   = $request->employee_name;
        $employee->employee_phone = $request->employee_phone;
        $employee->employee_email  = $request->employee_email;

        // ✅ Upload profile image if provided
        if ($request->hasFile('profile_image')) {

            $folderPath = base_path('../public_html/sharnam/profile'); // public_html/magazine/profile
            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0755, true);
            }

            // ✅ Delete old image (if exists)
            if (!empty($employee->profile_image)) {
                $oldPath = $folderPath . '/' . $employee->profile_image;
                if (File::exists($oldPath)) {
                    File::delete($oldPath);
                }
            }

            $image = $request->file('profile_image');
            $filename = 'cust_' . $employee->employee_id . '_' . time() . '.' . $image->getClientOriginalExtension();

            $image->move($folderPath, $filename);

            // ✅ Save filename in DB column
            $employee->profile_image = $filename; // change column name if different
        }

        $employee->save();

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated successfully',
            'data'    => $employee,
            'profile_image_url' => !empty($employee->profile_image)
                ? asset('/profile/' . $employee->profile_image)
                : null
        ]);
    }

}
