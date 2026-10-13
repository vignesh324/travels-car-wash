<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Trip;
use App\Services\InvoiceNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('datatable')) {
            $query = Invoice::with(['customer:id,name,company_name,type'])
                ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
                ->when($request->status, fn($q, $v) => $q->where('status', $v))
                ->when($request->source, fn($q, $v) => $q->where('source', $v))
                ->when($request->date_from, fn($q, $v) => $q->whereDate('invoice_date', '>=', $v))
                ->when($request->date_to, fn($q, $v) => $q->whereDate('invoice_date', '<=', $v));

            return DataTables::of($query)->make(true);
        }

        return response()->json([]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'     => 'required|exists:customers,id',
            'invoice_date'    => 'required|date',
            'due_date'        => 'nullable|date|after_or_equal:invoice_date',
            'source'          => 'required|in:travels,carwash,mixed',
            'place_of_supply' => 'nullable|string|max:100',
            'payment_terms'   => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'trip_ids'        => 'nullable|array',
            'trip_ids.*'      => 'exists:trips,id',
            'items'           => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.source'      => 'required|in:travels,carwash',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.discount'    => 'nullable|numeric|min:0',
            'items.*.tax_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        // Validate trip_ids not already invoiced
        if (!empty($validated['trip_ids'])) {
            $alreadyInvoiced = Trip::whereIn('id', $validated['trip_ids'])
                ->where('is_invoiced', true)->count();
            if ($alreadyInvoiced > 0) {
                return response()->json(['message' => 'One or more trips are already invoiced.'], 422);
            }
        }

        $profile = BusinessProfile::first();
        $businessState = $profile?->state ?? '';
        $placeOfSupply = $validated['place_of_supply'] ?? $businessState;

        $calc = Invoice::calculateTotals($validated['items'], $placeOfSupply, $businessState);

        $customer = \App\Models\Customer::findOrFail($validated['customer_id']);

        $invoice = DB::transaction(function () use ($validated, $calc, $customer, $profile, $placeOfSupply) {
            $number = app(InvoiceNumberService::class)->generate();

            $invoice = Invoice::create([
                'invoice_number'       => $number,
                'invoice_date'         => $validated['invoice_date'],
                'due_date'             => $validated['due_date'] ?? null,
                'customer_id'          => $validated['customer_id'],
                'source'               => $validated['source'],
                'customer_name'        => $customer->company_name ?? $customer->name,
                'customer_gstin'       => $customer->gstin,
                'customer_address'     => $customer->billing_address ?? $customer->address,
                'customer_state'       => $customer->state,
                'customer_state_code'  => $customer->state_code,
                'business_name'        => $profile?->business_name,
                'business_gstin'       => $profile?->gstin,
                'business_address'     => $profile?->address,
                'bank_details_snapshot'=> $profile?->bank_details,
                'place_of_supply'      => $placeOfSupply,
                'subtotal'             => $calc['subtotal'],
                'discount_total'       => $calc['discount_total'],
                'taxable_amount'       => $calc['taxable_amount'],
                'cgst_amount'          => $calc['cgst_amount'],
                'sgst_amount'          => $calc['sgst_amount'],
                'igst_amount'          => $calc['igst_amount'],
                'tax_total'            => $calc['tax_total'],
                'total_amount'         => $calc['total_amount'],
                'amount_paid'          => 0,
                'balance_due'          => $calc['total_amount'],
                'payment_terms'        => $validated['payment_terms'] ?? $profile?->default_payment_terms,
                'notes'                => $validated['notes'] ?? null,
                'footer'               => $profile?->invoice_footer,
                'status'               => 'issued',
                'issued_at'            => now(),
            ]);

            foreach ($calc['items'] as $item) {
                InvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
            }

            if (!empty($validated['trip_ids'])) {
                foreach ($validated['trip_ids'] as $tripId) {
                    $invoice->tripLinks()->create(['trip_id' => $tripId]);
                }
                Trip::whereIn('id', $validated['trip_ids'])->update(['is_invoiced' => true]);
            }

            activity()->causedBy(Auth::user())->performedOn($invoice)
                ->withProperties(['invoice_number' => $invoice->invoice_number, 'total' => $invoice->total_amount])
                ->log('Invoice created');

            return $invoice;
        });

        return response()->json($invoice->load(['customer:id,name,company_name', 'items']), 201);
    }

    public function show(Invoice $invoice)
    {
        return response()->json($invoice->load(['customer', 'items', 'trips', 'allocations.payment']));
    }

    public function update(Request $request, Invoice $invoice)
    {
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return response()->json(['message' => 'Cannot edit a paid or cancelled invoice.'], 422);
        }

        $validated = $request->validate([
            'invoice_date'    => 'required|date',
            'due_date'        => 'nullable|date',
            'place_of_supply' => 'nullable|string|max:100',
            'payment_terms'   => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.source'      => 'required|in:travels,carwash',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.discount'    => 'nullable|numeric|min:0',
            'items.*.tax_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        $profile = BusinessProfile::first();
        $businessState = $profile?->state ?? '';
        $placeOfSupply = $validated['place_of_supply'] ?? $invoice->place_of_supply ?? $businessState;

        $calc = Invoice::calculateTotals($validated['items'], $placeOfSupply, $businessState);

        DB::transaction(function () use ($invoice, $validated, $calc, $placeOfSupply) {
            $invoice->update([
                'invoice_date'    => $validated['invoice_date'],
                'due_date'        => $validated['due_date'] ?? null,
                'place_of_supply' => $placeOfSupply,
                'payment_terms'   => $validated['payment_terms'] ?? null,
                'notes'           => $validated['notes'] ?? null,
                'subtotal'        => $calc['subtotal'],
                'discount_total'  => $calc['discount_total'],
                'taxable_amount'  => $calc['taxable_amount'],
                'cgst_amount'     => $calc['cgst_amount'],
                'sgst_amount'     => $calc['sgst_amount'],
                'igst_amount'     => $calc['igst_amount'],
                'tax_total'       => $calc['tax_total'],
                'total_amount'    => $calc['total_amount'],
                'balance_due'     => max(0, $calc['total_amount'] - $invoice->amount_paid),
            ]);

            $invoice->items()->delete();
            foreach ($calc['items'] as $item) {
                InvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
            }
        });

        return response()->json($invoice->fresh()->load(['customer', 'items']));
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        if ($invoice->status === 'cancelled') {
            return response()->json(['message' => 'Invoice already cancelled.'], 422);
        }
        if ($invoice->amount_paid > 0) {
            return response()->json(['message' => 'Cannot cancel an invoice with recorded payments. Reverse payments first.'], 422);
        }

        $request->validate(['reason' => 'required|string|max:500']);

        DB::transaction(function () use ($invoice, $request) {
            // Unlink trips
            $tripIds = $invoice->tripLinks()->pluck('trip_id');
            if ($tripIds->isNotEmpty()) {
                Trip::whereIn('id', $tripIds)->update(['is_invoiced' => false]);
            }

            $invoice->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->reason,
                'cancelled_at' => now(),
            ]);

            activity()->causedBy(Auth::user())->performedOn($invoice)
                ->withProperties(['reason' => $request->reason])
                ->log('Invoice cancelled');
        });

        return response()->json(['message' => 'Invoice cancelled']);
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load(['customer', 'items', 'allocations.payment']);
        $profile = BusinessProfile::first();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', compact('invoice', 'profile'));
        $filename = 'Invoice-' . $invoice->invoice_number . '.pdf';

        return $pdf->download(str_replace('/', '-', $filename));
    }
}
