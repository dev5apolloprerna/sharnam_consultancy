<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCreditCollection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeCreditCollectionController extends Controller
{
    public function index(Request $request)
    {
        $data = EmployeeCreditCollection::query()
            ->when($request->filled('employee_id'), fn($q) => $q->where('employee_id', (int)$request->employee_id))
            ->when($request->filled('given_by'), fn($q) => $q->where('given_by', (int)$request->given_by))
            ->when($request->filled('date'), fn($q) => $q->where('date', $request->date))
            ->when($request->filled('isActive'), fn($q) => $q->where('isActive', (int)$request->isActive))
            ->orderByDesc('credit_id')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Credit collection list',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'    => ['required', 'integer', 'min:1'],
            'given_by'       => ['required', 'integer', 'min:0'], // 0 = admin
            'credit_amount'  => ['required', 'numeric', 'min:0.01'],
            'date'           => ['required', 'date_format:Y-m-d'],
            'isActive'       => ['nullable', Rule::in([0, 1])],
        ]);

        $row = EmployeeCreditCollection::create([
            'employee_id' => $validated['employee_id'],
            'given_by' => $validated['given_by'],
            'credit_amount' => $validated['credit_amount'],
            'date' => $validated['date'],
            'isActive' => $validated['isActive'] ?? 1,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Credit entry created',
            'data' => $row,
        ], 201);
    }

    public function show(Request $request)
    {
        $credit_id=$request->credit_id;
        $row = EmployeeCreditCollection::findOrFail($credit_id);

        return response()->json([
            'status' => true,
            'message' => 'Credit entry detail',
            'data' => $row,
        ]);
    }

    public function update(Request $request)
    {
        $credit_id=$request->credit_id;
        $row = EmployeeCreditCollection::findOrFail($credit_id);

        $validated = $request->validate([
            'employee_id'    => ['sometimes', 'integer', 'min:1'],
            'given_by'       => ['sometimes', 'integer', 'min:0'],
            'credit_amount'  => ['sometimes', 'numeric', 'min:0.01'],
            'date'           => ['sometimes', 'date_format:Y-m-d'],
            'isActive'       => ['sometimes', Rule::in([0, 1])],
        ]);

        $row->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Credit entry updated',
            'data' => $row->fresh(),
        ]);
    }

    public function destroy(Request $request)
    {
        $credit_id=$request->credit_id;
        $row = EmployeeCreditCollection::findOrFail($credit_id);
        $row->delete();

        return response()->json([
            'status' => true,
            'message' => 'Credit entry deleted',
        ]);
    }

    // Optional: soft-delete style (recommended)
    public function toggleActive(Request $request, int $credit_id)
    {
        $row = EmployeeCreditCollection::findOrFail($credit_id);

        $validated = $request->validate([
            'isActive' => ['required', Rule::in([0, 1])],
        ]);

        $row->isActive = (int)$validated['isActive'];
        $row->save();

        return response()->json([
            'status' => true,
            'message' => 'Status updated',
            'data' => $row->fresh(),
        ]);
    }
}