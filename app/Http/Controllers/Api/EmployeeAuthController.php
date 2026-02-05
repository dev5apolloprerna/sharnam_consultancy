<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\File;

use Illuminate\Http\Request;
use App\Models\EmployeeMaster;
use App\Models\EmployeeAttendance;

class EmployeeAuthController extends Controller
{
   public function login(Request $request)
    {
        $rules = [
            'employee_phone' => 'required|digits:10',
            'password' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $employee = EmployeeMaster::where('employee_phone', $request->employee_phone)
            ->where('isDelete', 0)
            ->where('iStatus', 1)
            ->first();

        
        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return response()->json(['status' => false, 'message' => 'Invalid login credentials'], 401);
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


        $token = JWTAuth::fromUser($employee);

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'isWorkStart' => $isWorkStart,
            'isWorkEnd' => $isWorkEnd,
            'customer' => $employee,
             'profile_image_url' => !empty($employee->profile_image)
                ? asset('/profile/' . $employee->profile_image)
                : null
        ]);
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
    
        return response()->json([
            'status' => true,
            'message' => 'Employee profile fetched successfully',
            'data' => [
                'employee_id'     => $employee->employee_id,
                'employee_name'   => $employee->employee_name,
                'employee_phone' => $employee->employee_phone,
                'employee_email'  => $employee->employee_email,
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
