<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeNotificationController extends Controller
{
    public function list(Request $request)
    {
        $employee = auth()->guard('api')->user();

        $data = DB::table('employee_notifications')
            ->where('employee_id', $employee->employee_id)
            ->orderByDesc('notification_id')
            ->limit((int) ($request->limit ?? 50))
            ->get();

        return response()->json([
            'status' => true,
            'unread_count' => DB::table('employee_notifications')
                ->where('employee_id', $employee->employee_id)
                ->where('is_read', 0)
                ->count(),
            'data' => $data,
        ]);
    }

    public function markRead(Request $request)
    {
        $employee = auth()->guard('api')->user();

        $request->validate([
            'notification_id' => 'nullable|integer',
        ]);

        $q = DB::table('employee_notifications')
            ->where('employee_id', $employee->employee_id);

        if ($request->filled('notification_id')) {
            $q->where('notification_id', $request->notification_id);
        }

        $q->update(['is_read' => 1, 'updated_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read.',
        ]);
    }
}
