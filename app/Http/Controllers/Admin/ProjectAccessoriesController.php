<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProjectAccessories;

class ProjectAccessoriesController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'site_id'        => 'required|integer',
            'accessories_id' => 'required|integer',
            'qty'            => 'required|integer|min:1',
            'date'           => 'required|date',
        ]);

        ProjectAccessories::create([
            'site_id'        => $request->site_id,
            'accessories_id' => $request->accessories_id,
            'qty'            => $request->qty,
            'date'           => $request->date,
        ]);

        return redirect()->back()->with('success', 'Accessories assigned successfully');
    }

    public function destroy($id)
    {
        ProjectAccessories::where('id', $id)->delete();

        return redirect()->back()->with('success', 'Record deleted successfully');
    }
}
