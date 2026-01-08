<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VehicleMaster;
use App\Models\EmployeeMaster;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $query = VehicleMaster::with('employee')->where('isDelete', 0);

        if ($request->vehicle_no) {
            $query->where('vehicle_no', 'like', '%' . $request->vehicle_no . '%');
        }

        if ($request->employee_name) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('employee_name', 'like', '%' . $request->employee_name . '%');
            });
        }

        $vehicles = $query->orderBy('vehicle_id', 'desc')->paginate(10);
        $employees = EmployeeMaster::orderBy('employee_name')->get();

        return view('admin.vehicle.index', compact('vehicles', 'employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vehicle_name' => 'required|max:200',
            'vehicle_no' => 'required|max:200',
            'employee_id' => 'required|integer',
        ]);

        VehicleMaster::create($request->all());

        return redirect()->back()->with('success', 'Vehicle added successfully.');
    }

    public function edit($id)
    {
        $vehicle = VehicleMaster::findOrFail($id);
        $employees = EmployeeMaster::orderBy('employee_name')->get();

        return response()->json([
            'vehicle' => $vehicle,
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'vehicle_name' => 'required|max:200',
            'vehicle_no' => 'required|max:200',
            'employee_id' => 'required|integer',
        ]);

        $vehicle = VehicleMaster::findOrFail($id);
        $vehicle->update($request->all());

        return redirect()->back()->with('success', 'Vehicle updated successfully.');
    }

    public function destroy($id)
    {
        VehicleMaster::where('vehicle_id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Vehicle deleted successfully.']);
    }

    public function bulkDelete(Request $request)
    {
        if ($request->has('ids')) {
            VehicleMaster::whereIn('vehicle_id', $request->ids)->delete();
        }

        return response()->json(['success' => true, 'message' => 'Selected vehicles deleted successfully.']);
    }
}
