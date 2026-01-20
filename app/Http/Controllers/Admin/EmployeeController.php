<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeMaster;
use App\Models\VehicleMaster;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeMaster::where('isDelete', 0);

        if ($request->employee_name) {
            $query->where('employee_name', 'like', '%' . $request->employee_name . '%');
        }

        if ($request->employee_phone) {
            $query->where('employee_phone', 'like', '%' . $request->employee_phone . '%');
        }

        if ($request->employee_email) {
            $query->where('employee_email', 'like', '%' . $request->employee_email . '%');
        }

        $employees = $query->orderBy('employee_id', 'desc')->paginate(10);
        return view('admin.employee.index', compact('employees'));
    }

    public function create()
    {
        $vehicles = VehicleMaster::orderBy('vehicle_name')->get();
        return view('admin.employee.add_edit', compact('vehicles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_name' => 'required|max:200',
            'employee_phone' => 'required|max:20',
            'employee_email' => 'required|email|max:200',
            'employee_address' => 'required|max:255',
            'basic_salary' => 'required|numeric',
            // 'vehicle_id' => 'nullable|integer',
            'designation' => 'required|max:200',
            'password' => 'required|min:6',
        ]);

        EmployeeMaster::create($request->all());

        return redirect()->route('admin.employee.index')->with('success', 'Employee added successfully.');
    }

    public function edit($id)
    {
        $employee = EmployeeMaster::findOrFail($id);
        $vehicles = VehicleMaster::orderBy('vehicle_name')->get();
        return view('admin.employee.add_edit', compact('employee', 'vehicles'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'employee_name' => 'required|max:200',
            'employee_phone' => 'required|max:20',
            'employee_email' => 'required|email|max:200',
            'employee_address' => 'required|max:255',
            'basic_salary' => 'required|numeric',
            // 'vehicle_id' => 'nullable|integer',
            'designation' => 'required|max:200',
        ]);

        $employee = EmployeeMaster::findOrFail($id);
        $employee->update($request->all());

        return redirect()->route('admin.employee.index')->with('success', 'Employee updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employee_master,employee_id',
            'new_password' => 'required|min:6|same:confirm_password',
        ]);

        EmployeeMaster::where('employee_id', $request->employee_id)
            ->update(['password' => Hash::make($request->new_password)]); // Add `employee_password` field in DB if not present

        return response()->json(['success' => true]);
    }


    public function destroy($id)
    {
        EmployeeMaster::where('employee_id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Employee deleted successfully.']);
    }

    public function bulkDelete(Request $request)
    {
        if ($request->has('ids')) {
            EmployeeMaster::whereIn('employee_id', $request->ids)->delete();
        }

        return response()->json(['success' => true, 'message' => 'Selected employees deleted successfully.']);
    }

    public function getVehicle($id)
    {
        $vehicle = VehicleMaster::where('employee_id', $id)->first();
        return response()->json(['vehicle' => $vehicle]);
    }

    // Save or update vehicle info (AJAX)
    public function saveVehicle(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employee_master,employee_id',
            'vehicle_name' => 'required|max:200',
            'vehicle_no' => 'required|max:200',
        ]);

        VehicleMaster::updateOrCreate(
            ['employee_id' => $request->employee_id],
            [
                'vehicle_name' => $request->vehicle_name,
                'vehicle_no' => $request->vehicle_no,
                'iStatus' => 1,
                'isDelete' => 0,
            ]
        );

        return response()->json(['success' => true]);
    }

}
