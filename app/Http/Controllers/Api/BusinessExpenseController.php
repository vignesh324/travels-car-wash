<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessExpense;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class BusinessExpenseController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('datatable')) {
            $query = BusinessExpense::with(['category:id,name,type'])
                ->when($request->source, fn($q, $v) => $q->where('source', $v))
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
            'category_id' => 'required|exists:expense_categories,id',
            'source' => 'required|in:general,carwash',
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

        $data['total_amount'] = $data['amount_before_tax'] + ($data['gst_amount'] ?? 0);

        $expense = BusinessExpense::create($data);

        return response()->json($expense->load('category:id,name'), 201);
    }

    public function show(BusinessExpense $businessExpense)
    {
        return response()->json($businessExpense->load('category'));
    }

    public function update(Request $request, BusinessExpense $businessExpense)
    {
        $data = $request->validate([
            'expense_date' => 'required|date',
            'category_id' => 'required|exists:expense_categories,id',
            'source' => 'required|in:general,carwash',
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

        $data['total_amount'] = $data['amount_before_tax'] + ($data['gst_amount'] ?? 0);

        $businessExpense->update($data);

        return response()->json($businessExpense->fresh()->load('category:id,name'));
    }

    public function destroy(BusinessExpense $businessExpense)
    {
        $businessExpense->setDeletedBy();
        $businessExpense->delete();

        return response()->json(['message' => 'Expense deleted']);
    }
}
