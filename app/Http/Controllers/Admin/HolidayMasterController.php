<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HolidayMaster;
use App\Services\HolidayAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HolidayMasterController extends Controller
{
    public function index(Request $request)
    {
        $query = HolidayMaster::query()->where('isDelete', 0);

        if ($request->filled('from_date')) {
            $query->whereDate('holiday_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('holiday_date', '<=', $request->to_date);
        }

        $holidays = $query->orderBy('holiday_date', 'asc')->paginate(20)->withQueryString();

        return view('admin.holiday_master.index', compact('holidays'));
    }

    public function store(Request $request, HolidayAttendanceService $holidayAttendanceService)
    {
        $request->validate([
            'holiday_name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        $holiday = HolidayMaster::create([
            'holiday_name' => $request->holiday_name,
            'holiday_date' => $request->holiday_date,
            'description' => $request->description,
            'iStatus' => 1,
            'isDelete' => 0,
        ]);

        $syncedCount = $holidayAttendanceService->syncHoliday($holiday);

        return back()->with('success', 'Holiday created successfully and marked as L for ' . $syncedCount . ' employees.');
    }

    public function update(Request $request, $holidayId, HolidayAttendanceService $holidayAttendanceService)
    {
        $request->validate([
            'holiday_name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        $holiday = HolidayMaster::where('holiday_id', $holidayId)->where('isDelete', 0)->firstOrFail();
        $oldHolidayDate = Carbon::parse($holiday->holiday_date)->toDateString();

        $holiday->update([
            'holiday_name' => $request->holiday_name,
            'holiday_date' => $request->holiday_date,
            'description' => $request->description,
        ]);

        $newHolidayDate = Carbon::parse($holiday->holiday_date)->toDateString();
        if ($oldHolidayDate !== $newHolidayDate) {
            $holidayAttendanceService->removeHolidayAttendance($oldHolidayDate);
        }

        $syncedCount = $holidayAttendanceService->syncHoliday($holiday);

        return back()->with('success', 'Holiday updated successfully and marked as L for ' . $syncedCount . ' employees.');
    }

    public function destroy($holidayId, HolidayAttendanceService $holidayAttendanceService)
    {
        $holiday = HolidayMaster::where('holiday_id', $holidayId)->where('isDelete', 0)->firstOrFail();
        $holidayDate = Carbon::parse($holiday->holiday_date)->toDateString();

        $holiday->update([
            'isDelete' => 1,
            'iStatus' => 0,
        ]);

        $removedCount = $holidayAttendanceService->removeHolidayAttendance($holidayDate);

        return back()->with('success', 'Holiday deleted successfully and removed ' . $removedCount . ' auto holiday attendance entries.');
    }
}
