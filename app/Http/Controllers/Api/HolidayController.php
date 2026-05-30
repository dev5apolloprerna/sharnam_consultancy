<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HolidayMaster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = HolidayMaster::query()
            ->where('isDelete', 0)
            ->where('iStatus', 1);

        if ($request->filled('from_date')) {
            $query->whereDate('holiday_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('holiday_date', '<=', $request->to_date);
        }

        if ($request->filled('year')) {
            $query->whereYear('holiday_date', $request->year);
        }

        if ($request->filled('month')) {
            $query->whereMonth('holiday_date', $request->month);
        }

        $holidays = $query
            ->orderBy('holiday_date', 'asc')
            ->get()
            ->map(function ($holiday) {
                return $this->formatHoliday($holiday);
            })
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Holiday list fetched successfully',
            'count' => $holidays->count(),
            'data' => $holidays,
        ]);
    }

    private function formatHoliday(HolidayMaster $holiday): array
    {
        $holidayDate = Carbon::parse($holiday->holiday_date);

        return [
            'holiday_id' => $holiday->holiday_id,
            'holiday_name' => $holiday->holiday_name,
            'holiday_date' => $holidayDate->toDateString(),
            'holiday_day' => $holidayDate->format('l'),
            'description' => $holiday->description,
        ];
    }
}
