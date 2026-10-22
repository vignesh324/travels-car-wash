<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Traits\AjaxDataTableTrait;

class CustomerController extends Controller
{
    use AjaxDataTableTrait;

    public function addEdit(?string $uuid = null)
    {
        $customer = $uuid ? Customer::where('uuid', $uuid)->firstOrFail() : null;
        $pageTitle = $customer ? 'Edit Customer' : 'Add Customer';
        $formUrl = $customer ? route('customers.update', $customer->id) : route('customers.store');
        $states = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi', 'Jammu & Kashmir', 'Ladakh', 'Puducherry'];
        $html = view('customers.add_edit', compact('customer', 'formUrl', 'states'))->render();

        return response()->json(compact('pageTitle', 'formUrl', 'html', 'customer', 'states'));
    }

    public function getData(Request $request)
    {
        $query = Customer::query()
            ->when($request->type,               fn($q, $v) => $q->where('type', $v))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('actions', fn (Customer $customer) =>
                    '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions">'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="customers.view" aria-label="' . e('View customer ' . $customer->displayName()) . '" title="View" data-view-url="' . e(route('customers.show', ['customer' => $customer->id])) . '"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></button>'
                    . '<button type="button" class="btn btn-outline-success btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="customers.statement" aria-label="' . e('Open statement for ' . $customer->displayName()) . '" title="Statement" data-customer-uuid="' . e($customer->uuid) . '" data-customer-name="' . e($customer->displayName()) . '"><i class="bi bi-file-earmark-text" aria-hidden="true"></i><span>Statement</span></button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="customers.edit" aria-label="' . e('Edit customer ' . $customer->displayName()) . '" title="Edit" data-customer-uuid="' . e($customer->uuid) . '"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>'
                    . '<button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="customers.delete" aria-label="' . e('Delete customer ' . $customer->displayName()) . '" title="Delete" data-customer-uuid="' . e($customer->uuid) . '" data-customer-name="' . e($customer->displayName()) . '"><i class="bi bi-trash" aria-hidden="true"></i><span>Delete</span></button>'
                    . '</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function index()
    {
        return view('customers.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'           => 'required|in:individual,corporate,walkin',
            'name'           => 'required|string|max:255',
            'company_name'   => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:15',
            'email'          => 'nullable|email',
            'address'        => 'nullable|string',
            'billing_address'=> 'nullable|string',
            'gstin'          => 'nullable|string|max:20',
            'state'          => 'nullable|string|max:100',
            'state_code'     => 'nullable|string|max:5',
            'payment_terms'  => 'nullable|string|max:255',
            'credit_limit'   => 'nullable|numeric|min:0',
            'notes'          => 'nullable|string',
            'is_active'      => 'boolean',
        ]);

        return response()->json(Customer::create($data), 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'type'           => 'required|in:individual,corporate,walkin',
            'name'           => 'required|string|max:255',
            'company_name'   => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:15',
            'email'          => 'nullable|email',
            'address'        => 'nullable|string',
            'billing_address'=> 'nullable|string',
            'gstin'          => 'nullable|string|max:20',
            'state'          => 'nullable|string|max:100',
            'state_code'     => 'nullable|string|max:5',
            'payment_terms'  => 'nullable|string|max:255',
            'credit_limit'   => 'nullable|numeric|min:0',
            'notes'          => 'nullable|string',
            'is_active'      => 'boolean',
        ]);

        $customer->update($data);

        return response()->json($customer->fresh());
    }

    public function destroy(Customer $customer)
    {
        $customer->setDeletedBy();
        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }

    public function restore(string $uuid)
    {
        $customer = Customer::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $customer->restore();
        $customer->update(['deleted_by' => null]);

        activity()->causedBy(Auth::user())->performedOn($customer)->log('Customer restored');

        return response()->json($customer);
    }

    public function statement(Request $request, Customer $customer)
    {
        $from = $request->date_from;
        $to   = $request->date_to ?? now()->toDateString();

        $invoicesQuery = $customer->invoices()->with('items')->orderBy('invoice_date');
        if ($from) $invoicesQuery->where('invoice_date', '>=', $from);
        if ($to)   $invoicesQuery->where('invoice_date', '<=', $to);
        $invoices = $invoicesQuery->get();

        $paymentsQuery = $customer->payments()->orderBy('payment_date');
        if ($from) $paymentsQuery->where('payment_date', '>=', $from);
        if ($to)   $paymentsQuery->where('payment_date', '<=', $to);
        $payments = $paymentsQuery->get();

        $priorInvoiced      = $from ? $customer->invoices()->where('status', '!=', 'cancelled')->where('invoice_date', '<', $from)->sum('total_amount') : 0;
        $priorPaid          = $from ? $customer->payments()->where('status', '!=', 'reversed')->where('payment_date', '<', $from)->sum('allocated_amount') : 0;
        $openingBalance     = round($priorInvoiced - $priorPaid, 2);
        $totalInvoiced      = round($customer->invoices()->where('status', '!=', 'cancelled')->sum('total_amount'), 2);
        $totalPaid          = round($customer->payments()->where('status', '!=', 'reversed')->sum('amount'), 2);
        $totalAllocated     = round($customer->payments()->where('status', '!=', 'reversed')->sum('allocated_amount'), 2);
        $advanceBalance     = round($customer->payments()->where('status', '!=', 'reversed')->sum('unallocated_amount'), 2);
        $outstandingBalance = round($customer->invoices()->where('status', '!=', 'cancelled')->sum('balance_due'), 2);

        return response()->json([
            'customer'            => $customer,
            'opening_balance'     => $openingBalance,
            'total_invoiced'      => $totalInvoiced,
            'total_paid'          => $totalPaid,
            'total_allocated'     => $totalAllocated,
            'advance_balance'     => $advanceBalance,
            'outstanding_balance' => $outstandingBalance,
            'closing_balance'     => $outstandingBalance,
            'invoices'            => $invoices,
            'payments'            => $payments,
        ]);
    }
}
