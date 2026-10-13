<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class VehicleExpenseController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('datatable')) {
            $query = VehicleExpense::with([
                'vehicle:id,name,registration_number',
                'category:id,name,type',
            ])
                ->when($request->vehicle_id, fn($q, $v) => $q->where('vehicle_id', $v))
                ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
                ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
                ->when($request->date_from, fn($q, $v) => $q->whereDate('expense_date', '>=', $v))
                ->when($request->date_to, fn($q, $v) => $q->whereDate('expense_date', '<=', $v));

            return DataTables::of($query)->make(true);
        }

        return response()->json([]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_date' => 'required|date',
            'vehicle_id' => 'required|exists:vehicles,id',
            'category_id' => 'required|exists:expense_categories,id',
            'description' => 'nullable|string|max:255',
            'vendor_name' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'amount_before_tax' => 'required|numeric|min:0',
            'gst_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'payment_mode' => 'required|string|max:50',
            'payment_status' => 'required|in:paid,unpaid,partial',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Server-side total validation
        $expectedTotal = $data['amount_before_tax'] + ($data['gst_amount'] ?? 0);
        if (abs($data['total_amount'] - $expectedTotal) > 0.01) {
            $data['total_amount'] = $expectedTotal;
        }

        if ($request->hasFile('receipt')) {
            $request->validate(['receipt' => 'file|mimes:jpg,jpeg,png,pdf|max:5120']);
            $data['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }

        $expense = VehicleExpense::create($data);

        return response()->json($expense->load(['vehicle:id,name,registration_number', 'category:id,name']), 201);
    }

    public function show(VehicleExpense $vehicleExpense)
    {
        return response()->json($vehicleExpense->load(['vehicle', 'category']));
    }

    public function update(Request $request, VehicleExpense $vehicleExpense)
    {
        $data = $request->validate([
            'expense_date' => 'required|date',
            'vehicle_id' => 'required|exists:vehicles,id',
            'category_id' => 'required|exists:expense_categories,id',
            'description' => 'nullable|string|max:255',
            'vendor_name' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'amount_before_tax' => 'required|numeric|min:0',
            'gst_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'payment_mode' => 'required|string|max:50',
            'payment_status' => 'required|in:paid,unpaid,partial',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $expectedTotal = $data['amount_before_tax'] + ($data['gst_amount'] ?? 0);
        if (abs($data['total_amount'] - $expectedTotal) > 0.01) {
            $data['total_amount'] = $expectedTotal;
        }

        if ($request->hasFile('receipt')) {
            $request->validate(['receipt' => 'file|mimes:jpg,jpeg,png,pdf|max:5120']);
            if ($vehicleExpense->receipt_path) {
                Storage::disk('public')->delete($vehicleExpense->receipt_path);
            }
            $data['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }

        $vehicleExpense->update($data);

        return response()->json($vehicleExpense->fresh()->load(['vehicle:id,name,registration_number', 'category:id,name']));
    }

    public function destroy(VehicleExpense $vehicleExpense)
    {
        $vehicleExpense->setDeletedBy();
        $vehicleExpense->delete();

        return response()->json(['message' => 'Expense deleted']);
    }
}
