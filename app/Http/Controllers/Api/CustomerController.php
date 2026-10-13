<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('datatable')) {
            $query = Customer::query()
                ->when($request->type, fn($q, $v) => $q->where('type', $v))
                ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

            return DataTables::of($query)->make(true);
        }

        return response()->json(
            Customer::where('is_active', true)->orderBy('name')
                ->get(['id', 'uuid', 'name', 'company_name', 'type', 'phone'])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:individual,corporate,walkin',
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:15',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'gstin' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:100',
            'state_code' => 'nullable|string|max:5',
            'payment_terms' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $customer = Customer::create($data);

        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'type' => 'required|in:individual,corporate,walkin',
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:15',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'gstin' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:100',
            'state_code' => 'nullable|string|max:5',
            'payment_terms' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
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
        if ($from) {
            $invoicesQuery->where('invoice_date', '>=', $from);
        }
        if ($to) {
            $invoicesQuery->where('invoice_date', '<=', $to);
        }
        $invoices = $invoicesQuery->get();

        $paymentsQuery = $customer->payments()->orderBy('payment_date');
        if ($from) {
            $paymentsQuery->where('payment_date', '>=', $from);
        }
        if ($to) {
            $paymentsQuery->where('payment_date', '<=', $to);
        }
        $payments = $paymentsQuery->get();

        $priorInvoiced = $from ? $customer->invoices()->where('status', '!=', 'cancelled')->where('invoice_date', '<', $from)->sum('total_amount') : 0;
        $priorPaid = $from ? $customer->payments()->where('status', '!=', 'reversed')->where('payment_date', '<', $from)->sum('allocated_amount') : 0;
        $openingBalance = round($priorInvoiced - $priorPaid, 2);

        $totalInvoiced = round($customer->invoices()->where('status', '!=', 'cancelled')->sum('total_amount'), 2);
        $totalPaid = round($customer->payments()->where('status', '!=', 'reversed')->sum('amount'), 2);
        $totalAllocated = round($customer->payments()->where('status', '!=', 'reversed')->sum('allocated_amount'), 2);
        $advanceBalance = round($customer->payments()->where('status', '!=', 'reversed')->sum('unallocated_amount'), 2);
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
