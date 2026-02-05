<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ConstructionSiteMaster;
use App\Models\EmployeeMaster;
use App\Models\SiteAssignEmployee;
use App\Models\VehicleMaster;
use App\Models\SiteStatus;

use Illuminate\Support\Facades\DB;

class ConstructionSiteController extends Controller
{
  public function index(Request $request)
    {
        $query = ConstructionSiteMaster::with(['assignedEmployees.employee','siteStatus'])
            ->where('isDelete', 0);
        $sites = $query->orderBy('site_id', 'desc')->paginate(10);
        $siteStatuses = SiteStatus::orderBy('site_status', 'asc')->get();

        
        return view('admin.construction_site.index', compact('sites','siteStatuses'));
    }

    public function search(Request $request)
    {
        $query = ConstructionSiteMaster::where('isDelete', 0);

        if ($request->site_name) {
            $query->where('site_name', 'like', '%' . $request->site_name . '%');
        }

        if ($request->site_status_id) {
            $query->where('site_status_id', $request->site_status_id);
        }

        $sites = $query->paginate(10);
        $siteStatuses = SiteStatus::orderBy('site_status', 'asc')->get();

        return view('admin.construction_site.index', compact('sites','siteStatuses'));
    }

    public function create()
    {
        return view('admin.construction_site.add_edit');
    }

    public function store(Request $request)
    {
        $request->validate([
            'site_name' => 'required|max:200',
            'site_address' => 'required|max:255',
            'site_pincode' => 'required|numeric',
            'site_radious_distance' => 'required|max:100',
            'site_status_id' => 'required|integer',
        ]);

        ConstructionSiteMaster::create($request->all());

        return redirect()->route('admin.construction-site.index')->with('success', 'Site created successfully.');
    }

    public function edit($id)
    {
        $site = ConstructionSiteMaster::findOrFail($id);
        return view('admin.construction_site.add_edit', compact('site'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'site_name' => 'required|max:200',
            'site_address' => 'required|max:255',
            'site_pincode' => 'required|numeric',
            'site_radious_distance' => 'required|max:100',
            'site_status_id' => 'required|integer',
        ]);

        $site = ConstructionSiteMaster::findOrFail($id);
        $site->update($request->all());

        return redirect()->route('admin.construction-site.index')->with('success', 'Site updated successfully.');
    }

    public function destroy($id)
    {
        ConstructionSiteMaster::where('site_id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Site deleted successfully.']);
    }

    public function bulkDelete(Request $request)
    {
        if ($request->has('ids')) {
            ConstructionSiteMaster::whereIn('site_id', $request->ids)->delete();
        }

        return response()->json(['success' => true, 'message' => 'Selected sites deleted successfully.']);
    }
    public function employees($site_id)
    {
        $employees = EmployeeMaster::where('isDelete', 0)->orderBy('employee_name')->get(['employee_id', 'employee_name']);

        $assigned = SiteAssignEmployee::where('site_id', $site_id)
            ->where('isDelete', 0)
            ->pluck('site_emp_id')
            ->toArray();

        return response()->json([
            'employees' => $employees,
            'assigned' => $assigned
        ]);
    }

    // Save assigned employees (AJAX)
    public function assignEmployees(Request $request)
    {
        $site_id = $request->site_id;
        $employee_ids = $request->employee_ids ?? [];

        // First, soft delete all current assignments
        SiteAssignEmployee::where('site_id', $site_id)->delete();

        // Then, insert new ones
        foreach ($employee_ids as $emp_id) {
            SiteAssignEmployee::create([
                'site_id' => $site_id,
                'site_emp_id' => $emp_id,
                'iStatus' => 1,
                'isDelete' => 0
            ]);
        }

        return response()->json(['success' => true]);
    }
    public function employeeVehiclePage($site_id)
    {

        $site = ConstructionSiteMaster::findOrFail($site_id);
        $employees = EmployeeMaster::where('iStatus', 1)->where('isDelete', 0)->orderBy('employee_name')->get();
        $vehicles = VehicleMaster::where('iStatus', 1)->where('isDelete', 0)->orderBy('vehicle_name')->get();

        $assignments = DB::table('construction_employee_vehicle as sev')
            ->join('employee_master as e', 'e.employee_id', '=', 'sev.employee_id')
            ->leftJoin('vehicle_master as v', 'v.vehicle_id', '=', 'sev.vehicle_id')
            ->where('sev.construction_id', $site_id)
            ->where('sev.isDelete', 0)
            ->select('sev.id', 'e.employee_name', 'v.vehicle_name', 'v.vehicle_no')
            ->get();


        return view('admin.construction_site.employee_vehicle', compact('site', 'employees', 'vehicles', 'assignments'));
    }

    public function saveAssignment(Request $request)
{
    $request->validate([
        'site_id' => 'required|exists:construction_site_master,site_id',
        'employee_id' => 'required|exists:employee_master,employee_id',
        'vehicle_id' => 'nullable|exists:vehicle_master,vehicle_id',
    ]);

    $alreadyAssigned = DB::table('construction_employee_vehicle')
        ->where('construction_id', $request->site_id)
        ->where('employee_id', $request->employee_id)
        ->where('isDelete', 0)
        ->exists();

        $alreadyAssignedVehicle = DB::table('construction_employee_vehicle')
        ->where('construction_id', $request->site_id)
        ->where('vehicle_id', $request->vehicle_id)
        ->where('isDelete', 0)
        ->exists();

    if ($alreadyAssigned) {
        return back()->with('error', 'This employee is already assigned to this site.');
    }
    if ($alreadyAssignedVehicle) {
        return back()->with('error', 'This Vehicle is already assigned to other employee.');
    }

    DB::table('construction_employee_vehicle')->insert([
        'construction_id' => $request->site_id,
        'employee_id' => $request->employee_id,
        'vehicle_id' => $request->assign_vehicle ? $request->vehicle_id : null,
        'iStatus' => 1,
        'isDelete' => 0,
        'created_at' => now(),
    ]);

    return back()->with('success', 'Assignment saved successfully.');
}



    public function deleteAssignment($id)
    {
        DB::table('construction_employee_vehicle')
            ->where('id', $id)
            ->update(['isDelete' => 1]);

        return back()->with('success', 'Assignment removed successfully.');
    }


}
