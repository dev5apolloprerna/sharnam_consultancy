<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Accessories;

class AccessoriesController extends Controller
{
    public function index()
    {
        $accessories = Accessories::orderBy('accessories_id', 'desc')->paginate(10);
        return view('admin.accessories.index', compact('accessories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'accessories_name' => 'required|string|max:255',
        ]);

        Accessories::create([
            'accessories_name' => $request->accessories_name,
        ]);

        return redirect()->back()->with('success', 'Accessory added successfully');
    }

    public function edit($id)
    {
        $accessory = Accessories::findOrFail($id);
        return response()->json($accessory);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'accessories_name' => 'required|string|max:255',
        ]);

        $accessory = Accessories::findOrFail($id);
        $accessory->update([
            'accessories_name' => $request->accessories_name,
        ]);

        return redirect()->back()->with('success', 'Accessory updated successfully');
    }

    public function destroy($id)
    {
        Accessories::where('accessories_id', $id)->delete();
        return response()->json(['success' => true]);
    }


    public function bulkDelete(Request $request)
    {
        Accessories::whereIn('accessories_id', $request->ids)->delete();
        return response()->json(['success' => true]);
    }
}
