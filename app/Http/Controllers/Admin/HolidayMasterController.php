<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HolidayMaster;
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

    public function store(Request $request)
    {
        $request->validate([
            'holiday_name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        HolidayMaster::create([
            'holiday_name' => $request->holiday_name,
            'holiday_date' => $request->holiday_date,
            'description' => $request->description,
            'iStatus' => 1,
            'isDelete' => 0,
        ]);

        return back()->with('success', 'Holiday created successfully.');
    }

    public function update(Request $request, $holidayId)
    {
        $request->validate([
            'holiday_name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        $holiday = HolidayMaster::where('holiday_id', $holidayId)->where('isDelete', 0)->firstOrFail();
        $holiday->update([
            'holiday_name' => $request->holiday_name,
            'holiday_date' => $request->holiday_date,
            'description' => $request->description,
        ]);

        return back()->with('success', 'Holiday updated successfully.');
    }

    public function destroy($holidayId)
    {
        $holiday = HolidayMaster::where('holiday_id', $holidayId)->where('isDelete', 0)->firstOrFail();
        $holiday->update([
            'isDelete' => 1,
            'iStatus' => 0,
        ]);

        return back()->with('success', 'Holiday deleted successfully.');
    }
}
