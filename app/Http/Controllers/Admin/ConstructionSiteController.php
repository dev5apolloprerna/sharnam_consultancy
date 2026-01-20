<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ConstructionSiteMaster;
use App\Models\EmployeeMaster;
use App\Models\SiteAssignEmployee;

class ConstructionSiteController extends Controller
{
    public function index(Request $request)
    {
        $query = ConstructionSiteMaster::with(['assignedEmployees.employee'])->where('isDelete', 0);

        if ($request->has('site_name')) {
            $query->where('site_name', 'like', '%' . $request->site_name . '%');
        }

        if ($request->has('site_status_id')) {
            $query->where('site_status_id', $request->site_status_id);
        }

        $sites = $query->orderBy('site_id', 'desc')->paginate(10);
       

        return view('admin.construction_site.index', compact('sites'));
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

}
