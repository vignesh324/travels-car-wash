<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Trip;
use App\Services\InvoiceNumberService;
use App\Services\PaymentAllocationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use App\Http\Traits\AjaxDataTableTrait;

class InvoiceController extends Controller
{
    use AjaxDataTableTrait;

    public function addEdit(?string $uuid = null)
    {
        $invoice = $uuid ? Invoice::with(['items', 'trips'])->where('uuid', $uuid)->firstOrFail() : null;
        $pageTitle = $invoice ? 'Edit Invoice' : 'Create Invoice';
        $formUrl = $invoice ? route('invoices.update', $invoice->id) : route('invoices.store');
        $customers = Customer::dropdownOptions();
        $trips = [];
        $html = view('invoices.add_edit', compact('invoice', 'formUrl', 'customers', 'trips'))->render();

        return response()->json(compact('pageTitle', 'formUrl', 'html', 'invoice', 'customers', 'trips'));
    }

    public function eligibleTrips(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $trips = Trip::query()
            ->where('customer_id', $validated['customer_id'])
            ->where('status', 'completed')
            ->where('is_invoiced', false)
            ->whereBetween('trip_date', [$validated['date_from'], $validated['date_to']])
            ->with('vehicle:id,name,type,registration_number')
            ->orderBy('trip_date')
            ->get(['id', 'trip_number', 'trip_date', 'customer_id', 'vehicle_id', 'route', 'pickup_location', 'drop_location', 'total_amount']);

        return response()->json($trips);
    }

    public function getData(Request $request)
    {
        $query = Invoice::with(['customer:id,name,company_name,type'])
            ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->status, function ($q, $value) {
                $statuses = is_array($value) ? $value : explode(',', $value);
                $q->whereIn('status', $statuses);
            })
            ->when($request->source,      fn($q, $v) => $q->where('source', $v))
            ->when($request->date_from,   fn($q, $v) => $q->whereDate('invoice_date', '>=', $v))
            ->when($request->date_to,     fn($q, $v) => $q->whereDate('invoice_date', '<=', $v));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('actions', fn (Invoice $invoice) =>
                    '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions">'
                    . '<a href="' . e(route('invoices.show', ['invoice' => $invoice->uuid])) . '" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" aria-label="' . e('View invoice ' . $invoice->invoice_number) . '" title="View"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></a>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="invoices.pdf" aria-label="' . e('Download PDF for invoice ' . $invoice->invoice_number) . '" title="PDF" data-pdf-url="' . e(route('invoices.pdf', ['invoice' => $invoice->uuid])) . '"><i class="bi bi-filetype-pdf" aria-hidden="true"></i><span>PDF</span></button>'
                    . (in_array($invoice->status, ['paid', 'cancelled'], true)
                        ? ''
                        : '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="invoices.edit" aria-label="' . e('Edit invoice ' . $invoice->invoice_number) . '" title="Edit" data-invoice-uuid="' . e($invoice->uuid) . '"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>'
                            . '<button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="invoices.cancel" aria-label="' . e('Cancel invoice ' . $invoice->invoice_number) . '" title="Cancel" data-invoice-uuid="' . e($invoice->uuid) . '" data-invoice-number="' . e($invoice->invoice_number) . '"><i class="bi bi-x-circle" aria-hidden="true"></i><span>Cancel</span></button>')
                    . '</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function index()
    {
        $customers = Customer::dropdownOptions();

        return view('invoices.index', compact('customers'));
    }

    public function store(Request $request)
    {
        $hasTrips = !empty($request->input('trip_ids', []));
        $validated = $request->validate([
            'customer_id'             => 'required|exists:customers,id',
            'invoice_date'            => 'required|date',
            'due_date'                => 'nullable|date|after_or_equal:invoice_date',
            'source'                  => 'required|in:travels,carwash,mixed',
            'place_of_supply'         => 'nullable|string|max:100',
            'payment_terms'           => 'nullable|string|max:255',
            'notes'                   => 'nullable|string',
            'trip_ids'                => 'nullable|array',
            'trip_ids.*'              => 'integer|distinct|exists:trips,id',
            'trip_date_from'          => 'nullable|date',
            'trip_date_to'            => 'nullable|date|after_or_equal:trip_date_from',
            'items'                   => $hasTrips ? 'nullable|array' : 'required|array|min:1',
            'items.*.description'     => 'required|string|max:500',
            'items.*.source'          => 'required|in:travels,carwash',
            'items.*.quantity'        => 'required|numeric|min:0.01',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.discount'        => 'nullable|numeric|min:0',
            'items.*.tax_rate'        => 'nullable|numeric|min:0|max:100',
        ]);

        $profile       = BusinessProfile::first();
        $businessState = $profile?->state ?? '';
        $placeOfSupply = $validated['place_of_supply'] ?? $businessState;
        $customer      = Customer::findOrFail($validated['customer_id']);

        $invoice = DB::transaction(function () use ($validated, $customer, $profile, $placeOfSupply, $businessState) {
            $trips = new \Illuminate\Database\Eloquent\Collection();
            if (!empty($validated['trip_ids'])) {
                $trips = Trip::with('vehicle')
                    ->whereIn('id', $validated['trip_ids'])
                    ->lockForUpdate()
                    ->get();

                $validTrips = $trips->count() === count($validated['trip_ids'])
                    && $trips->every(fn (Trip $trip) =>
                        (int) $trip->customer_id === (int) $validated['customer_id']
                        && $trip->status === 'completed'
                        && !$trip->is_invoiced
                        && (!isset($validated['trip_date_from']) || $trip->trip_date->toDateString() >= $validated['trip_date_from'])
                        && (!isset($validated['trip_date_to']) || $trip->trip_date->toDateString() <= $validated['trip_date_to'])
                    );

                if (!$validTrips) {
                    throw ValidationException::withMessages([
                        'trip_ids' => 'Trips must be completed, uninvoiced trips for this customer within the selected date range.',
                    ]);
                }
            }

            $items = array_merge($validated['items'] ?? [], Invoice::tripSummaryItems($trips));
            if ($items === []) {
                throw ValidationException::withMessages(['items' => 'Add at least one invoice item or select trips.']);
            }
            $calc = Invoice::calculateTotals($items, $placeOfSupply, $businessState);

            $number  = app(InvoiceNumberService::class)->generate();

            $invoice = Invoice::create([
                'invoice_number'        => $number,
                'invoice_date'          => $validated['invoice_date'],
                'due_date'              => $validated['due_date'] ?? null,
                'customer_id'           => $validated['customer_id'],
                'source'                => $validated['source'],
                'customer_name'         => $customer->company_name ?? $customer->name,
                'customer_gstin'        => $customer->gstin,
                'customer_address'      => $customer->billing_address ?? $customer->address,
                'customer_state'        => $customer->state,
                'customer_state_code'   => $customer->state_code,
                'business_name'         => $profile?->business_name,
                'business_gstin'        => $profile?->gstin,
                'business_address'      => $profile?->address,
                'bank_details_snapshot' => $profile?->bank_details,
                'place_of_supply'       => $placeOfSupply,
                'subtotal'              => $calc['subtotal'],
                'discount_total'        => $calc['discount_total'],
                'taxable_amount'        => $calc['taxable_amount'],
                'cgst_amount'           => $calc['cgst_amount'],
                'sgst_amount'           => $calc['sgst_amount'],
                'igst_amount'           => $calc['igst_amount'],
                'tax_total'             => $calc['tax_total'],
                'total_amount'          => $calc['total_amount'],
                'amount_paid'           => 0,
                'balance_due'           => $calc['total_amount'],
                'payment_terms'         => $validated['payment_terms'] ?? $profile?->default_payment_terms,
                'notes'                 => $validated['notes'] ?? null,
                'footer'                => $profile?->invoice_footer,
                'status'                => 'issued',
                'issued_at'             => now(),
            ]);

            foreach ($calc['items'] as $item) {
                InvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
            }

            if ($trips->isNotEmpty()) {
                foreach ($trips as $trip) {
                    $tripId = $trip->id;
                    $invoice->tripLinks()->create(['trip_id' => $tripId]);
                }
                Trip::whereIn('id', $trips->modelKeys())->update(['is_invoiced' => true]);
                app(PaymentAllocationService::class)->allocateInitialPayments($invoice, $trips);
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
        $invoice->load(['customer', 'items', 'trips.vehicle', 'payments']);
        $profile = BusinessProfile::first();

        return view('invoices.show', compact('invoice', 'profile'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return response()->json(['message' => 'Cannot edit a paid or cancelled invoice.'], 422);
        }

        $validated = $request->validate([
            'invoice_date'        => 'required|date',
            'due_date'            => 'nullable|date',
            'place_of_supply'     => 'nullable|string|max:100',
            'payment_terms'       => 'nullable|string|max:255',
            'notes'               => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.source'      => 'required|in:travels,carwash',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.discount'    => 'nullable|numeric|min:0',
            'items.*.tax_rate'    => 'nullable|numeric|min:0|max:100',
            'items.*.vehicle_type' => 'nullable|string|max:255',
            'items.*.route'        => 'nullable|string|max:255',
            'items.*.service_days' => 'nullable|integer|min:1',
        ]);

        $profile       = BusinessProfile::first();
        $businessState = $profile?->state ?? '';
        $placeOfSupply = $validated['place_of_supply'] ?? $invoice->place_of_supply ?? $businessState;
        $calc          = Invoice::calculateTotals($validated['items'], $placeOfSupply, $businessState);

        DB::transaction(function () use ($invoice, $validated, $calc, $placeOfSupply) {
            $invoice->update([
                'invoice_date'   => $validated['invoice_date'],
                'due_date'       => $validated['due_date'] ?? null,
                'place_of_supply'=> $placeOfSupply,
                'payment_terms'  => $validated['payment_terms'] ?? null,
                'notes'          => $validated['notes'] ?? null,
                'subtotal'       => $calc['subtotal'],
                'discount_total' => $calc['discount_total'],
                'taxable_amount' => $calc['taxable_amount'],
                'cgst_amount'    => $calc['cgst_amount'],
                'sgst_amount'    => $calc['sgst_amount'],
                'igst_amount'    => $calc['igst_amount'],
                'tax_total'      => $calc['tax_total'],
                'total_amount'   => $calc['total_amount'],
                'balance_due'    => max(0, $calc['total_amount'] - $invoice->amount_paid),
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
            $tripIds = $invoice->tripLinks()->pluck('trip_id');
            if ($tripIds->isNotEmpty()) {
                Trip::whereIn('id', $tripIds)->update(['is_invoiced' => false]);
            }

            $invoice->update([
                'status'               => 'cancelled',
                'cancellation_reason'  => $request->reason,
                'cancelled_at'         => now(),
            ]);

            activity()->causedBy(Auth::user())->performedOn($invoice)
                ->withProperties(['reason' => $request->reason])
                ->log('Invoice cancelled');
        });

        return response()->json(['message' => 'Invoice cancelled']);
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load(['customer', 'items', 'trips.vehicle', 'payments']);
        $profile  = BusinessProfile::first();
        $filename = 'Invoice-' . str_replace('/', '-', $invoice->invoice_number) . '.pdf';

        return Pdf::loadView('pdf.invoice', compact('invoice', 'profile'))->download($filename);
    }

    public function pdfSignedUrl(Invoice $invoice)
    {
        $url = URL::temporarySignedRoute('pdf.invoice', now()->addMinutes(5), ['invoice' => $invoice->id]);

        return response()->json(['url' => $url]);
    }

    public function destroy(Invoice $invoice)
    {
        return response()->json(['message' => 'Invoices cannot be deleted. Use cancel instead.'], 422);
    }

    public function pdfSigned(Invoice $invoice)
    {
        $invoice->load(['customer', 'items', 'trips.vehicle', 'payments']);
        $profile  = BusinessProfile::first();
        $filename = 'Invoice-' . str_replace('/', '-', $invoice->invoice_number) . '.pdf';

        return Pdf::loadView('pdf.invoice', compact('invoice', 'profile'))->download($filename);
    }
}
