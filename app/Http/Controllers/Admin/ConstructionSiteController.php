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
        $siteNames = ConstructionSiteMaster::where('isDelete', 0)
            ->orderBy('site_name', 'asc')
            ->get(['site_id', 'site_name']);

        
        return view('admin.construction_site.index', compact('sites','siteStatuses','siteNames'));
    }

    public function search(Request $request)
    {
        $query = ConstructionSiteMaster::with(['assignedEmployees.employee','siteStatus'])
            ->where('isDelete', 0);

        if ($request->site_name) {
            $query->where('site_name', $request->site_name);
        }

        if ($request->site_status_id) {
            $query->where('site_status_id', $request->site_status_id);
        }

        $sites = $query->orderBy('site_id', 'desc')->paginate(10);
        $siteStatuses = SiteStatus::orderBy('site_status', 'asc')->get();
        $siteNames = ConstructionSiteMaster::where('isDelete', 0)
            ->orderBy('site_name', 'asc')
            ->get(['site_id', 'site_name']);
            
        return view('admin.construction_site.index', compact('sites','siteStatuses','siteNames'));
    }

    public function create()
    {
        $siteStatuses = SiteStatus::orderBy('site_status', 'asc')->get();

        return view('admin.construction_site.add_edit', compact('siteStatuses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'site_name' => 'required|max:200',
            'site_address' => 'required|max:255',
            'site_pincode' => 'required|numeric',
            'site_radious_distance' => 'required|max:100',
            'site_status_id' => 'required|exists:site_status,site_status_id',
            'iStatus' => 'required|in:0,1',
            'longitude' => 'required',
            'latitude' => 'required',
        ]);

        ConstructionSiteMaster::create($request->all());

        return redirect()->route('admin.construction-site.index')->with('success', 'Site created successfully.');
    }

    public function edit($id)
    {
        $site = ConstructionSiteMaster::findOrFail($id);
        $siteStatuses = SiteStatus::orderBy('site_status', 'asc')->get();

        return view('admin.construction_site.add_edit', compact('site', 'siteStatuses'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'site_name' => 'required|max:200',
            'site_address' => 'required|max:255',
            'site_pincode' => 'required|numeric',
            'site_radious_distance' => 'required|max:100',
            'site_status_id' => 'required|exists:site_status,site_status_id',
            'iStatus' => 'required|in:0,1',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $site = ConstructionSiteMaster::findOrFail($id);
        $site->update($request->all());

        if ((int) $request->iStatus === 0 || $this->isClosedSiteStatus($request->site_status_id)) {
            $this->removeSiteEmployeeAndVehicleAssignments($id);
        }

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
        // Remove old employee assignments for this site
        SiteAssignEmployee::where('site_id', $site_id)->delete();

        // Remove vehicle assignments of employees who are no longer selected
        DB::table('construction_employee_vehicle')
            ->where('construction_id', $site_id)
            ->when(count($employee_ids) > 0, function ($q) use ($employee_ids) {
                $q->whereNotIn('employee_id', $employee_ids);
            })
            ->update([
                'isDelete' => 1,
                'iStatus'  => 0,
            ]);

        foreach ($employee_ids as $emp_id) {
            SiteAssignEmployee::create([
                'site_id'          => $site_id,
                'site_emp_id'      => $emp_id,
                'is_site_manager'  => isset($managerFlags[$emp_id]) && (int)$managerFlags[$emp_id] === 1 ? 1 : 0,
                'iStatus'          => 1,
                'isDelete'         => 0,
            ]);

            // Auto get vehicle assigned to this employee from old site or vehicle master
            $autoVehicleId = $this->getEmployeeAutoVehicleId($emp_id, $site_id);

            // Check current site employee vehicle assignment
            $existingAssignment = DB::table('construction_employee_vehicle')
                ->where('construction_id', $site_id)
                ->where('employee_id', $emp_id)
                ->first();

            if ($existingAssignment) {
                DB::table('construction_employee_vehicle')
                    ->where('id', $existingAssignment->id)
                    ->update([
                        'vehicle_id' => $autoVehicleId,
                        'iStatus'    => 1,
                        'isDelete'   => 0,
                    ]);
            } else {
                DB::table('construction_employee_vehicle')->insert([
                    'construction_id' => $site_id,
                    'employee_id'     => $emp_id,
                    'vehicle_id'      => $autoVehicleId,
                    'iStatus'         => 1,
                    'isDelete'        => 0,
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Employees assigned successfully. Vehicle auto assigned where available.'
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

    DB::beginTransaction();

    try {
        $siteId = $request->site_id;
        $employeeId = $request->employee_id;

        // If vehicle not selected manually, auto get employee's old/default vehicle
        $vehicleId = $request->filled('vehicle_id')
            ? $request->vehicle_id
            : $this->getEmployeeAutoVehicleId($employeeId, $siteId);

        // Check employee already active on same site
        $activeEmployeeAssignment = DB::table('construction_employee_vehicle')
            ->where('construction_id', $siteId)
            ->where('employee_id', $employeeId)
            ->where('isDelete', 0)
            ->first();

        if ($activeEmployeeAssignment) {
            DB::rollBack();
            return back()->with('error', 'This employee is already assigned to this site.');
        }

        if (!empty($vehicleId)) {
            // Vehicle cannot be assigned to another active employee
            $vehicleAssignedToOtherEmployee = DB::table('construction_employee_vehicle')
                ->where('vehicle_id', $vehicleId)
                ->where('employee_id', '!=', $employeeId)
                ->where('isDelete', 0)
                ->exists();

            if ($vehicleAssignedToOtherEmployee) {
                DB::rollBack();
                return back()->with('error', 'This vehicle is already assigned to another employee.');
            }
        }

        // Important fix:
        // Check old soft-deleted same record because unique key still blocks duplicate insert
        $oldDeletedAssignment = DB::table('construction_employee_vehicle')
            ->where('construction_id', $siteId)
            ->where('employee_id', $employeeId)
            ->where(function ($q) use ($vehicleId) {
                if (empty($vehicleId)) {
                    $q->whereNull('vehicle_id');
                } else {
                    $q->where('vehicle_id', $vehicleId);
                }
            })
            ->first();

        if ($oldDeletedAssignment) {
            DB::table('construction_employee_vehicle')
                ->where('id', $oldDeletedAssignment->id)
                ->update([
                    'iStatus'    => 1,
                    'isDelete'   => 0,
                    // 'updated_at' => now(),
                ]);
        } else {
            DB::table('construction_employee_vehicle')->insert([
                'construction_id' => $siteId,
                'employee_id'     => $employeeId,
                'vehicle_id'      => $vehicleId,
                'iStatus'         => 1,
                'isDelete'        => 0,
                'created_at'      => now(),
            ]);
        }

        DB::commit();

        return back()->with('success', 'Employee and vehicle assigned successfully.');
    } catch (\Exception $e) {
        DB::rollBack();

        return back()->with('error', 'Something went wrong: ' . $e->getMessage());
    }
}

    public function deleteAssignment($id)
    {
        DB::table('construction_employee_vehicle')
            ->where('id', $id)
            ->update(['isDelete' => 1, 'iStatus' => 0]);

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

            if ($this->isClosedSiteStatus($request->site_status_id)) {
                $this->removeSiteEmployeeAndVehicleAssignments($request->site_id);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Site status changed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }
    private function getEmployeeAutoVehicleId($employeeId, $siteId = null)
{
    // First priority: last assigned vehicle from construction_employee_vehicle
    $query = DB::table('construction_employee_vehicle')
        ->where('employee_id', $employeeId)
        ->whereNotNull('vehicle_id')
        ->where('isDelete', 0);

    if (!empty($siteId)) {
        $query->where('construction_id', '!=', $siteId);
    }

    $vehicleId = $query->orderBy('id', 'desc')->value('vehicle_id');

    if (!empty($vehicleId)) {
        return $vehicleId;
    }

    // Second priority: vehicle_master employee_id mapping
    return DB::table('vehicle_master')
        ->where('employee_id', $employeeId)
        ->where('iStatus', 1)
        ->where('isDelete', 0)
        ->orderBy('vehicle_id', 'desc')
        ->value('vehicle_id');
}

private function isClosedSiteStatus($siteStatusId)
{
    $statusName = DB::table('site_status')
        ->where('site_status_id', $siteStatusId)
        ->value('site_status');

    $statusName = strtolower(trim($statusName ?? ''));

    return in_array($statusName, [
        'inactive',
        'complete',
        'completed',
        'close',
        'closed',
        'finish',
        'finished',
    ]);
}

private function removeSiteEmployeeAndVehicleAssignments($siteId)
{
    // Remove assigned employees
    DB::table('site_assign_employee')
        ->where('site_id', $siteId)
        ->update([
            'isDelete' => 1,
            'iStatus'  => 0,
        ]);

    // Remove assigned employee vehicles
    DB::table('construction_employee_vehicle')
        ->where('construction_id', $siteId)
        ->update([
            'isDelete' => 1,
            'iStatus'  => 0,
        ]);
}

}