<?php

namespace App\Http\Controllers;

use App\Models\BusinessExpense;
use App\Models\ExpenseCategory;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Traits\AjaxDataTableTrait;

class ExpenseController extends Controller
{
    use AjaxDataTableTrait;

    public function addEdit(string $type, ?int $id = null)
    {
        abort_unless(in_array($type, ['vehicle', 'business'], true), 404);
        $isVehicle = $type === 'vehicle';
        $expense = $id
            ? ($isVehicle ? VehicleExpense::findOrFail($id) : BusinessExpense::findOrFail($id))
            : null;
        $pageTitle = ($expense ? 'Edit ' : 'Add ') . ($isVehicle ? 'Vehicle' : 'Business') . ' Expense';
        $formUrl = $isVehicle
            ? ($expense ? route('expenses.vehicle.update', $expense->id) : route('expenses.vehicle.store'))
            : ($expense ? route('expenses.business.update', $expense->id) : route('expenses.business.store'));
        $vehicles = Vehicle::dropdownOptions($isVehicle ? $expense?->vehicle_id : null);
        $categories = ExpenseCategory::dropdownOptions($type, $expense?->category_id);
        $html = view('expenses.add_edit', compact('type', 'expense', 'formUrl', 'vehicles', 'categories'))->render();

        return response()->json(compact('pageTitle', 'formUrl', 'html', 'type', 'expense', 'vehicles', 'categories'));
    }

    public function getData(Request $request)
    {
        $request->validate(['type' => 'nullable|in:vehicle,business']);

        if ($request->input('type') === 'business') {
            return $this->getBusinessExpenses($request);
        }

        return $this->getVehicleExpenses($request);
    }

    public function getVehicleData(Request $request)
    {
        return $this->getVehicleExpenses($request);
    }

    protected function getVehicleExpenses(Request $request)
    {
        $query = VehicleExpense::with(['vehicle:id,name,registration_number', 'category:id,name,type'])
            ->when($request->vehicle_id,     fn($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->category_id,    fn($q, $v) => $q->where('category_id', $v))
            ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
            ->when($request->date_from,      fn($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($request->date_to,        fn($q, $v) => $q->whereDate('expense_date', '<=', $v));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('actions', fn (VehicleExpense $expense) =>
                    '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions">'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="expenses.view" aria-label="' . e('View vehicle expense ' . $expense->id) . '" title="View" data-expense-type="vehicle" data-view-url="' . e(route('expenses.vehicle.show', ['vehicleExpense' => $expense->id])) . '"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="expenses.edit" aria-label="' . e('Edit vehicle expense ' . $expense->id) . '" title="Edit" data-expense-type="vehicle" data-expense-id="' . e($expense->id) . '"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>'
                    . '<button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="expenses.delete" aria-label="' . e('Delete vehicle expense ' . $expense->id) . '" title="Delete" data-expense-type="vehicle" data-expense-id="' . e($expense->id) . '"><i class="bi bi-trash" aria-hidden="true"></i><span>Delete</span></button>'
                    . '</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function getBusinessData(Request $request)
    {
        return $this->getBusinessExpenses($request);
    }

    protected function getBusinessExpenses(Request $request)
    {
        $query = BusinessExpense::with(['category:id,name,type'])
            ->when($request->source,         fn($q, $v) => $q->where('source', $v))
            ->when($request->category_id,    fn($q, $v) => $q->where('category_id', $v))
            ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
            ->when($request->date_from,      fn($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($request->date_to,        fn($q, $v) => $q->whereDate('expense_date', '<=', $v));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('actions', fn (BusinessExpense $expense) =>
                    '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions">'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="expenses.view" aria-label="' . e('View business expense ' . $expense->id) . '" title="View" data-expense-type="business" data-view-url="' . e(route('expenses.business.show', ['businessExpense' => $expense->id])) . '"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="expenses.edit" aria-label="' . e('Edit business expense ' . $expense->id) . '" title="Edit" data-expense-type="business" data-expense-id="' . e($expense->id) . '"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>'
                    . '<button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="expenses.delete" aria-label="' . e('Delete business expense ' . $expense->id) . '" title="Delete" data-expense-type="business" data-expense-id="' . e($expense->id) . '"><i class="bi bi-trash" aria-hidden="true"></i><span>Delete</span></button>'
                    . '</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function index(Request $request)
    {
        if ($request->wantsJson() && $request->input('list') === 'categories') {
            return response()->json(ExpenseCategory::dropdownOptions($request->input('type')));
        }

        $vehicles = Vehicle::dropdownOptions();
        $vehicleCategories = ExpenseCategory::dropdownOptions('vehicle');
        $businessCategories = ExpenseCategory::dropdownOptions('business');

        return view('expenses.index', compact('vehicles', 'vehicleCategories', 'businessCategories'));
    }

    // ── Vehicle Expenses ──────────────────────────────────────────────────────

    public function storeVehicle(Request $request)
    {
        $data = $request->validate([
            'expense_date'      => 'required|date',
            'vehicle_id'        => 'required|exists:vehicles,id',
            'category_id'       => 'required|exists:expense_categories,id',
            'description'       => 'nullable|string|max:255',
            'vendor_name'       => 'nullable|string|max:255',
            'reference_number'  => 'nullable|string|max:100',
            'amount_before_tax' => 'required|numeric|min:0',
            'gst_amount'        => 'nullable|numeric|min:0',
            'total_amount'      => 'required|numeric|min:0',
            'payment_mode'      => 'required|string|max:50',
            'payment_status'    => 'required|in:paid,unpaid,partial',
            'payment_date'      => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $expected = $data['amount_before_tax'] + ($data['gst_amount'] ?? 0);
        if (abs($data['total_amount'] - $expected) > 0.01) {
            $data['total_amount'] = $expected;
        }

        if ($request->hasFile('receipt')) {
            $request->validate(['receipt' => 'file|mimes:jpg,jpeg,png,pdf|max:5120']);
            $data['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }

        $expense = VehicleExpense::create($data);

        return response()->json($expense->load(['vehicle:id,name,registration_number', 'category:id,name']), 201);
    }

    public function showVehicle(VehicleExpense $vehicleExpense)
    {
        return response()->json($vehicleExpense->load(['vehicle', 'category']));
    }

    public function updateVehicle(Request $request, VehicleExpense $vehicleExpense)
    {
        $data = $request->validate([
            'expense_date'      => 'required|date',
            'vehicle_id'        => 'required|exists:vehicles,id',
            'category_id'       => 'required|exists:expense_categories,id',
            'description'       => 'nullable|string|max:255',
            'vendor_name'       => 'nullable|string|max:255',
            'reference_number'  => 'nullable|string|max:100',
            'amount_before_tax' => 'required|numeric|min:0',
            'gst_amount'        => 'nullable|numeric|min:0',
            'total_amount'      => 'required|numeric|min:0',
            'payment_mode'      => 'required|string|max:50',
            'payment_status'    => 'required|in:paid,unpaid,partial',
            'payment_date'      => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $expected = $data['amount_before_tax'] + ($data['gst_amount'] ?? 0);
        if (abs($data['total_amount'] - $expected) > 0.01) {
            $data['total_amount'] = $expected;
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

    public function destroyVehicle(VehicleExpense $vehicleExpense)
    {
        $vehicleExpense->setDeletedBy();
        $vehicleExpense->delete();

        return response()->json(['message' => 'Expense deleted']);
    }

    // ── Business Expenses ─────────────────────────────────────────────────────

    public function storeBusiness(Request $request)
    {
        $data = $request->validate([
            'expense_date'      => 'required|date',
            'category_id'       => 'required|exists:expense_categories,id',
            'source'            => 'required|in:general,carwash',
            'description'       => 'nullable|string|max:255',
            'vendor_name'       => 'nullable|string|max:255',
            'reference_number'  => 'nullable|string|max:100',
            'amount_before_tax' => 'required|numeric|min:0',
            'gst_amount'        => 'nullable|numeric|min:0',
            'total_amount'      => 'required|numeric|min:0',
            'payment_mode'      => 'required|string|max:50',
            'payment_status'    => 'required|in:paid,unpaid,partial',
            'payment_date'      => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $data['total_amount'] = $data['amount_before_tax'] + ($data['gst_amount'] ?? 0);

        $expense = BusinessExpense::create($data);

        return response()->json($expense->load('category:id,name'), 201);
    }

    public function showBusiness(BusinessExpense $businessExpense)
    {
        return response()->json($businessExpense->load('category'));
    }

    public function updateBusiness(Request $request, BusinessExpense $businessExpense)
    {
        $data = $request->validate([
            'expense_date'      => 'required|date',
            'category_id'       => 'required|exists:expense_categories,id',
            'source'            => 'required|in:general,carwash',
            'description'       => 'nullable|string|max:255',
            'vendor_name'       => 'nullable|string|max:255',
            'reference_number'  => 'nullable|string|max:100',
            'amount_before_tax' => 'required|numeric|min:0',
            'gst_amount'        => 'nullable|numeric|min:0',
            'total_amount'      => 'required|numeric|min:0',
            'payment_mode'      => 'required|string|max:50',
            'payment_status'    => 'required|in:paid,unpaid,partial',
            'payment_date'      => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $data['total_amount'] = $data['amount_before_tax'] + ($data['gst_amount'] ?? 0);

        $businessExpense->update($data);

        return response()->json($businessExpense->fresh()->load('category:id,name'));
    }

    public function destroyBusiness(BusinessExpense $businessExpense)
    {
        $businessExpense->setDeletedBy();
        $businessExpense->delete();

        return response()->json(['message' => 'Expense deleted']);
    }

    // ── Expense Categories ────────────────────────────────────────────────────

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'type'        => 'required|in:vehicle,general,carwash',
            'description' => 'nullable|string',
            'is_capital'  => 'boolean',
            'sort_order'  => 'nullable|integer',
        ]);

        return response()->json(ExpenseCategory::create($data), 201);
    }

    public function updateCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'type'        => 'required|in:vehicle,general,carwash',
            'description' => 'nullable|string',
            'is_capital'  => 'boolean',
            'is_active'   => 'boolean',
            'sort_order'  => 'nullable|integer',
        ]);

        $expenseCategory->update($data);

        return response()->json($expenseCategory->fresh());
    }
}
