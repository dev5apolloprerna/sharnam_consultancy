<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ConstructionSiteMaster;
use App\Models\EmployeeMaster;
use App\Models\SiteAssignEmployee;
use App\Models\VehicleMaster;
use App\Models\SiteStatus;
use App\Models\Accessories;
use App\Models\ProjectAccessories;
use Illuminate\Support\Facades\Auth;


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
            'longitude' => 'required',
            'latitude' => 'required',
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
            'latitude' => 'required',
            'longitude' => 'required',
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
    $employees = EmployeeMaster::where('isDelete', 0)
        ->orderBy('employee_name')
        ->get(['employee_id', 'employee_name']);

    $assignedRows = SiteAssignEmployee::where('site_id', $site_id)
        ->where('isDelete', 0)
        ->get(['site_emp_id', 'is_site_manager']);

    $assigned = $assignedRows->map(function ($row) {
        return [
            'employee_id' => (int) $row->site_emp_id,
            'is_site_manager' => (int) $row->is_site_manager,
        ];
    })->values();

    return response()->json([
        'employees' => $employees,
        'assigned'  => $assigned,
    ]);
}

public function assignEmployees(Request $request)
{
    $request->validate([
        'site_id' => 'required|exists:construction_site_master,site_id',
        'employee_ids' => 'nullable|array',
        'employee_ids.*' => 'integer|exists:employee_master,employee_id',
        'is_site_manager' => 'nullable|array',
    ]);

    $site_id = $request->site_id;
    $employee_ids = $request->employee_ids ?? [];
    $managerFlags = $request->is_site_manager ?? [];

    DB::beginTransaction();

    try {
        // Soft delete or hard delete based on your current table handling
        SiteAssignEmployee::where('site_id', $site_id)->delete();

        foreach ($employee_ids as $emp_id) {
            SiteAssignEmployee::create([
                'site_id'          => $site_id,
                'site_emp_id'      => $emp_id,
                'is_site_manager'  => isset($managerFlags[$emp_id]) && (int)$managerFlags[$emp_id] === 1 ? 1 : 0,
                'iStatus'          => 1,
                'isDelete'         => 0,
            ]);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Employees assigned successfully.'
        ]);
    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong while assigning employees.',
            'error'   => $e->getMessage(),
        ], 500);
    }
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
    public function employeeAccessoriesPage($site_id)
    {

        $site = ConstructionSiteMaster::findOrFail($site_id);
        $employees = EmployeeMaster::where('iStatus', 1)->where('isDelete', 0)->orderBy('employee_name')->get();
        $accessories = Accessories::orderBy('accessories_name')->get();

  
        $assignments = ProjectAccessories::join(
            'accessories_master',
            'accessories_master.accessories_id',
            '=',
            'project_accessories.accessories_id'
        )
        ->where('project_accessories.site_id', $site_id)
        ->select(
            'project_accessories.*',
            'accessories_master.accessories_name'
        )
        ->get();


        return view('admin.construction_site.site_accessories', compact('site', 'employees', 'assignments','accessories'));
    }

   public function saveAssignment(Request $request)
    {
        $request->validate([
            'site_id'     => 'required|exists:construction_site_master,site_id',
            'employee_id' => 'required|exists:employee_master,employee_id',
            'vehicle_id'  => 'nullable|exists:vehicle_master,vehicle_id',
        ]);
    
        $alreadyAssigned = DB::table('construction_employee_vehicle')
            ->where('construction_id', $request->site_id)
            ->where('employee_id', $request->employee_id)
            ->where('isDelete', 0)
            ->exists();
    
        if ($alreadyAssigned) {
            return back()->with('error', 'This employee is already assigned to this site.');
        }
    
        if (!empty($request->vehicle_id)) {
            $alreadyAssignedVehicle = DB::table('construction_employee_vehicle')
                ->where('vehicle_id', $request->vehicle_id)
                ->where('isDelete', 0)
                ->exists();
    
            if ($alreadyAssignedVehicle) {
                return back()->with('error', 'This vehicle is already assigned to another employee.');
            }
        }
    
        DB::table('construction_employee_vehicle')->insert([
            'construction_id' => $request->site_id,
            'employee_id'     => $request->employee_id,
            'vehicle_id'      => $request->filled('vehicle_id') ? $request->vehicle_id : null,
            'iStatus'         => 1,
            'isDelete'        => 0,
        ]);
    
        return back()->with('success', 'Assign Vehicle successfully.');
    }



    public function deleteAssignment($id)
    {
        DB::table('construction_employee_vehicle')
            ->where('id', $id)
            ->update(['isDelete' => 1]);

        return back()->with('success', 'Assignment removed successfully.');
    }

 public function changeStatus(Request $request)
    {
        $request->validate([
            'site_id'         => 'required|exists:construction_site_master,site_id',
            'site_status_id'  => 'required|exists:site_status,site_status_id',
        ]);

        DB::beginTransaction();

        try {
            $site = DB::table('construction_site_master')
                ->where('site_id', $request->site_id)
                ->first();

            if (!$site) {
                return redirect()->back()->with('error', 'Site not found.');
            }

            // Optional: prevent duplicate history if status is same
            if ((int) $site->site_status_id === (int) $request->site_status_id) {
                return redirect()->back()->with('error', 'Selected status is already active for this site.');
            }

            // Update site current status
            DB::table('construction_site_master')
                ->where('site_id', $request->site_id)
                ->update([
                    'site_status_id' => $request->site_status_id,
                    'updated_at'     => now(),
                ]);

            // Insert history
            DB::table('site_status_history')->insert([
                'site_id'         => $request->site_id,
                'site_status_id'  => $request->site_status_id,
                'date_time'       => now(),
                'employee_id'     => Auth::user()->employee_id ?? Auth::user()->id,
                'role_id'         => Auth::user()->role_id ?? null,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Site status changed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

}
