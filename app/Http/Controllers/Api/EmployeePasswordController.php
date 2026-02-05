<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\EmployeeMaster;

class EmployeePasswordController extends Controller
{
    private string $emailColumn = 'employee_email';

    // Temp password expiry (optional)
    private int $tempExpireMinutes = 60;

    public function forgot(Request $request)
    {
        $request->validate([
            'employee_email' => 'required|email',
        ]);

        $email = $request->employee_email;

        $employee = EmployeeMaster::where($this->emailColumn, $email)->first();
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found.',
            ], 404);
        }

        // ✅ Generate a temporary password
        $tempPassword = $this->generateTempPassword(10);

        // ✅ Update password in DB (hashed)
        $employee->password = Hash::make($tempPassword);

        // Optional fields (recommended)
        // Add these columns via migration (shown below) if you want:
        $employee->must_reset_password = 1;
        $employee->temp_password_set_at = now();

        $employee->save();

        // ✅ Email data
        $data = [
            'employee'     => $employee,
            'tempPassword' => $tempPassword,
            'minutes'      => $this->tempExpireMinutes,
        ];

        $msg = [
            'FromMail' => config('mail.from.address'),
            'Title'    => config('mail.from.name'),
            'ToEmail'  => $email,
            'Subject'  => 'Your Temporary Password',
        ];

        try {
            Mail::send('emails.employee_reset_password', $data, function ($message) use ($msg) {
                $message->from($msg['FromMail'], $msg['Title']);
                $message->to($msg['ToEmail'])->subject($msg['Subject']);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mail failed',
                'error'   => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Temporary password sent successfully.',
        ]);
    }
    private function generateTempPassword(): string
    {
        return (string) random_int(100000, 999999);
    }


    public function changePassword(Request $request)
    {
        $employee = auth()->guard('api')->user();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorised'
            ], 401);
        }

        $request->validate([
            'current_password' => 'required|string|min:6',
            'new_password'     => 'required|string|min:6|different:current_password|confirmed',
            // requires: new_password_confirmation
        ]);

        // ✅ Check current password
        if (!Hash::check($request->current_password, $employee->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect'
            ], 422);
        }

        // ✅ Update password
        $employee->password = Hash::make($request->new_password);
        $employee->save();

        return response()->json([
            'status'  => true,
            'message' => 'Password changed successfully'
        ]);
    }

}
