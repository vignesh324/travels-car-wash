<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpenseCategory::where('is_active', true)
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->orderBy('sort_order')
            ->orderBy('name');

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:vehicle,general,carwash',
            'description' => 'nullable|string',
            'is_capital' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $category = ExpenseCategory::create($data);

        return response()->json($category, 201);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:vehicle,general,carwash',
            'description' => 'nullable|string',
            'is_capital' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $expenseCategory->update($data);

        return response()->json($expenseCategory->fresh());
    }
}
