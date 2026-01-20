<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeLocationHistory;
use App\Models\EmployeeMaster;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EmployeeLocationAlert; // We'll create this

class EmployeeLocationController extends Controller
{

      public function trackLocation(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employee_master,employee_id',
            'latitude' => 'required|string',
            'longitude' => 'required|string',
            'comments' => 'nullable|string',
        ]);

        EmployeeLocationHistory::create([
            'employee_id' => $request->employee_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'comments' => $request->comments ?? '',
            'iStatus' => 1,
            'isDelete' => 0,
            'created_at' => now()
        ]);

        $this->sendLocationNotificationToAdmin($request->employee_id, $request->latitude, $request->longitude);

        return response()->json(['success' => true, 'message' => 'Location stored successfully.']);
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