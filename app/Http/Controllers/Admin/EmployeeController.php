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
        $employees = EmployeeMaster::where('isDelete', 0)->orderBy('employee_id', 'desc')->paginate(10);
        
        $employee=$request->employee_name;
        $phone=$request->employee_phone;
        $email=$request->employee_email;
        return view('admin.employee.index', compact('employees','employee','phone','email'));
    }
    public function search(Request $request)
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
        
        $employee=$request->employee_name;
        $phone=$request->employee_phone;
        $email=$request->employee_email;
        return view('admin.employee.index', compact('employees','employee','phone','email'));
    }

    public function create()
    {
        $vehicles = VehicleMaster::orderBy('vehicle_name')->get();
        return view('admin.employee.add_edit', compact('vehicles'));
    }

        public function store(Request $request)
    {
        $request->validate([
            'employee_name'    => 'required|max:200',
            'employee_phone'   => 'required|max:20',
            'employee_email'   => 'required|email|max:200|unique:employee_master,employee_email',
            'employee_address' => 'required|max:255',
            'basic_salary'     => 'required|numeric',
            'designation'      => 'required|max:200',
            'joining_date'     => 'required|date',
            'password'         => 'required|min:6',
        ]);
    
        $employee = new EmployeeMaster();
        $employee->employee_name    = $request->employee_name;
        $employee->employee_phone   = $request->employee_phone;
        $employee->employee_email   = $request->employee_email;
        $employee->employee_address = $request->employee_address;
        $employee->basic_salary     = $request->basic_salary;
        $employee->designation      = $request->designation;
        $employee->joining_date     = $request->joining_date;
        $employee->password         = Hash::make($request->password);
    
        // if you have other fields, set them here
        // $employee->vehicle_id = $request->vehicle_id;
    
        $employee->save();
        
    
        // Generate member_id = joining year + auto employee_id
        $joiningYear = date('Y', strtotime($employee->joining_date));
        $employee->member_id = $joiningYear . str_pad($employee->employee_id, 4, '0', STR_PAD_LEFT);
        $employee->save();
    
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
        $employee = EmployeeMaster::findOrFail($id);
    
        $request->validate([
            'employee_name'    => 'required|max:200',
            'employee_phone'   => 'required|max:20',
            'employee_email'   => 'required|email|max:200|unique:employee_master,employee_email,' . $employee->employee_id . ',employee_id',
            'employee_address' => 'required|max:255',
            'basic_salary'     => 'required|numeric',
            'designation'      => 'required|max:200',
            'joining_date'     => 'required|date',
            'password'         => 'nullable|min:6',
        ]);
    
        $employee->employee_name    = $request->employee_name;
        $employee->employee_phone   = $request->employee_phone;
        $employee->employee_email   = $request->employee_email;
        $employee->employee_address = $request->employee_address;
        $employee->basic_salary     = $request->basic_salary;
        $employee->designation      = $request->designation;
        $employee->joining_date     = $request->joining_date;
    
        // if you have other fields, set them here
        // $employee->vehicle_id = $request->vehicle_id;
    
        if (!empty($request->password)) {
            $employee->password = Hash::make($request->password);
        }
    
        // Re-generate member_id if joining date changed
        $joiningYear = date('Y', strtotime($request->joining_date));
        $employee->member_id = $joiningYear . str_pad($employee->employee_id, 4, '0', STR_PAD_LEFT);
    
        $employee->save();
    
        return redirect()->route('admin.employee.index')->with('success', 'Employee updated successfully.');
    }

    public function empchangePassword(Request $request)
    {
        dd($request);
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
     public function resign(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employee_master,employee_id',
            'resign_date' => 'required|date',
            'last_working_date' => 'required|date|after_or_equal:resign_date',
        ]);

        $employee = EmployeeMaster::findOrFail((int) $request->employee_id);

        $lastWorkingDate = \Carbon\Carbon::parse($request->last_working_date)->toDateString();
        $isInactive = $lastWorkingDate <= now()->toDateString();

        $employee->resign_date = $request->resign_date;
        $employee->last_working_date = $lastWorkingDate;

        if ($isInactive) {
            $employee->iStatus = 0;
        }

        $employee->save();

        return response()->json([
            'success' => true,
            'message' => 'Resignation details saved successfully.',
            'employee_status' => $employee->iStatus ? 'Active' : 'Inactive',
        ]);
    }


}
