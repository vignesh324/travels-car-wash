@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_number)

@section('content')
@php
    $trips = $invoice->trips
        ->sortBy(fn ($trip) => ($trip->trip_date?->format('Y-m-d') ?? '') . '|' . ($trip->trip_number ?? ''))
        ->values();
    $tripTypes = $trips->countBy(fn ($trip) => trim((string) $trip->trip_type) !== '' ? trim((string) $trip->trip_type) : 'Not recorded');
    $tripDates = $trips->pluck('trip_date')->filter();
    $billingPeriod = $tripDates->isNotEmpty()
        ? $tripDates->min()->format('M j, Y') . ($tripDates->min()->toDateString() === $tripDates->max()->toDateString() ? '' : ' – ' . $tripDates->max()->format('M j, Y'))
        : 'Service period not recorded';
    $businessName = $invoice->business_name ?: $profile?->business_name ?: 'ADU Travels';
    $businessAddress = $invoice->business_address ?: $profile?->address;
    $businessGstin = $invoice->business_gstin ?: $profile?->gstin;
    $businessPhone = $profile?->phone;
    $businessEmail = $profile?->email;
    $customer = $invoice->customer;
    $customerName = $invoice->customer_name ?: $customer?->displayName() ?: 'Customer';
    $customerAddress = $invoice->customer_address ?: $customer?->billing_address ?: $customer?->address;
    $customerState = $invoice->customer_state ?: $customer?->state;
    $customerStateCode = $invoice->customer_state_code ?: $customer?->state_code;
    $customerGstin = $invoice->customer_gstin ?: $customer?->gstin;
    $paymentTerms = $invoice->payment_terms ?: $profile?->default_payment_terms ?: 'As agreed with the customer.';
    $bankDetails = is_array($invoice->bank_details_snapshot) && count($invoice->bank_details_snapshot)
        ? $invoice->bank_details_snapshot
        : ($profile?->bank_details ?? []);
    $canEdit = !in_array($invoice->status, ['paid', 'cancelled'], true);
    $canCancel = !in_array($invoice->status, ['paid', 'cancelled'], true) && (float) $invoice->amount_paid <= 0;
    $statusClass = match ($invoice->status) {
        'paid' => 'text-bg-success',
        'partial' => 'text-bg-warning',
        'cancelled' => 'text-bg-danger',
        default => 'text-bg-primary',
    };
    $formatMoney = fn ($value) => '₹' . number_format((float) $value, 2);
    $detailItems = $invoice->items;
@endphp

<div class="invoice-detail-page">
    <div class="invoice-toolbar d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <a href="{{ route('invoices.index') }}" class="small text-decoration-none text-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to invoices
            </a>
            <h1 class="h3 fw-bold mt-2 mb-1">Invoice details</h1>
            <p class="text-secondary small mb-0">Review the invoice, billing statement and payment record.</p>
        </div>
        <nav class="d-flex flex-wrap gap-2" aria-label="Invoice actions">
            @if($canEdit)
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('invoices.index', ['action' => 'edit', 'uuid' => $invoice->uuid]) }}">
                    <i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit
                </a>
            @endif
            @if($canCancel)
                <a class="btn btn-outline-danger btn-sm" href="{{ route('invoices.index', ['action' => 'cancel', 'uuid' => $invoice->uuid, 'number' => $invoice->invoice_number]) }}">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancel
                </a>
            @endif
            <a class="btn btn-primary btn-sm" href="{{ route('invoices.pdf', ['invoice' => $invoice->uuid]) }}">
                <i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>Download PDF
            </a>
        </nav>
    </div>

    <article class="invoice-paper">
        <header class="invoice-heading">
            <div class="invoice-business">
                <p class="invoice-label mb-1">Service provider</p>
                <h2 class="h4 fw-bold mb-2">{{ $businessName }}</h2>
                @if($businessAddress)<p class="mb-1">{{ $businessAddress }}</p>@endif
                @if($businessPhone || $businessEmail)
                    <p class="mb-1">
                        @if($businessPhone)Phone: {{ $businessPhone }}@endif
                        @if($businessPhone && $businessEmail)<span class="mx-1">·</span>@endif
                        @if($businessEmail){{ $businessEmail }}@endif
                    </p>
                @endif
                @if($businessGstin)<p class="mb-0"><strong>GSTIN:</strong> {{ $businessGstin }}</p>@endif
            </div>
            <div class="invoice-meta text-md-end">
                <p class="invoice-label mb-1">{{ $trips->isNotEmpty() ? 'Monthly corporate taxi invoice' : 'Tax invoice' }}</p>
                <h1 class="h4 fw-bold mb-2">{{ $invoice->invoice_number }}</h1>
                <div class="small text-secondary">
                    Invoice date: {{ $invoice->invoice_date?->format('M j, Y') ?? '—' }}<br>
                    @if($invoice->due_date)Due date: {{ $invoice->due_date->format('M j, Y') }}<br>@endif
                    Billing period: {{ $billingPeriod }}
                </div>
                <span class="badge {{ $statusClass }} mt-2">{{ ucfirst($invoice->status) }}</span>
            </div>
        </header>

        <section class="invoice-parties row g-3 py-4 border-bottom" aria-label="Invoice parties">
            <div class="col-12 col-md-6">
                <div class="party-block h-100">
                    <p class="invoice-label mb-2">Bill to</p>
                    <h3 class="h6 fw-bold mb-2">{{ $customerName }}</h3>
                    @if($customerAddress)<p class="small mb-1">{{ $customerAddress }}</p>@endif
                    @if($customerState)
                        <p class="small mb-1">{{ $customerState }}@if($customerStateCode) ({{ $customerStateCode }})@endif</p>
                    @endif
                    @if($customerGstin)<p class="small mb-0"><strong>GSTIN:</strong> {{ $customerGstin }}</p>@endif
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="party-block h-100">
                    <p class="invoice-label mb-2">Invoice details</p>
                    <dl class="invoice-facts mb-0">
                        <div><dt>Place of supply</dt><dd>{{ $invoice->place_of_supply ?: '—' }}</dd></div>
                        <div><dt>Source</dt><dd>{{ ucfirst($invoice->source ?: '—') }}</dd></div>
                        @if($invoice->customer_gstin)<div><dt>Customer GSTIN</dt><dd>{{ $invoice->customer_gstin }}</dd></div>@endif
                    </dl>
                </div>
            </div>
        </section>

        @if($trips->isNotEmpty())
            <section class="invoice-section py-4 border-bottom" aria-labelledby="trip-statement-heading">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                    <div>
                        <p class="invoice-label mb-1">Linked trip statement</p>
                        <h2 id="trip-statement-heading" class="h6 fw-bold mb-0">Daily trip statement</h2>
                    </div>
                    <div class="small text-secondary text-md-end">
                        <div>{{ $trips->count() }} billable trip {{ $trips->count() === 1 ? 'record' : 'records' }} · {{ $billingPeriod }}</div>
                        <div class="mt-1">
                            @foreach($tripTypes as $type => $count)
                                <span class="me-2">{{ $type }}: <strong>{{ $count }}</strong></span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="table-responsive invoice-table-wrap">
                    <table class="table table-sm align-middle invoice-table invoice-trip-table mb-0">
                        <thead>
                            <tr><th>Service date</th><th>Vehicle</th><th>Driver</th><th>Trip type / journey</th><th class="text-end">Trips</th><th class="text-end">Trip amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach($trips as $trip)
                                <tr>
                                    <td>{{ $trip->trip_date?->format('M j, Y') ?? '—' }}@if($trip->trip_number)<div class="small text-secondary">{{ $trip->trip_number }}</div>@endif</td>
                                    <td>
                                        {{ $trip->vehicle?->registration_number ?: $trip->vehicle?->name ?: 'Vehicle not recorded' }}
                                        @if($trip->vehicle?->type)<div class="small text-secondary">{{ $trip->vehicle->type }}</div>@endif
                                    </td>
                                    <td>{{ $trip->driver_name ?: '—' }}</td>
                                    <td>
                                        <strong>{{ trim((string) $trip->trip_type) !== '' ? $trip->trip_type : 'Not recorded' }}</strong>
                                        <div class="small text-secondary">
                                            @if($trip->pickup_location || $trip->drop_location)
                                                {{ $trip->pickup_location ?: '—' }} → {{ $trip->drop_location ?: '—' }}
                                            @else
                                                {{ $trip->route ?: $trip->description ?: 'Journey not recorded' }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end">1</td>
                                    <td class="text-end fw-semibold">{{ $formatMoney($trip->total_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="small text-secondary mt-2">Total linked trip amount: <strong>{{ $formatMoney($trips->sum(fn ($trip) => (float) $trip->total_amount)) }}</strong></div>
            </section>
        @endif

        <section class="invoice-section py-4 border-bottom" aria-labelledby="items-heading">
            <p class="invoice-label mb-1">Invoice accounting</p>
            <h2 id="items-heading" class="h6 fw-bold mb-2">Persisted invoice line items</h2>
            @if($trips->isNotEmpty())
                <p class="small text-secondary mb-3">These billing lines include trip charges summarized in the operational statement above.</p>
            @endif
            @if($detailItems->isNotEmpty())
                <div class="table-responsive invoice-table-wrap">
                    <table class="table table-sm align-middle invoice-table invoice-items-table mb-0">
                        <thead>
                            <tr><th>Description</th><th class="text-end">Qty / days</th><th class="text-end">Rate</th><th class="text-end">Discount</th><th class="text-end">Taxable</th><th class="text-end">GST</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach($detailItems as $item)
                                <tr>
                                    <td>
                                        {{ $item->description }}
                                        @if(($item->vehicle_type && $item->vehicle_type !== $item->description) || $item->route)
                                            <div class="small text-secondary">
                                                @if($item->vehicle_type && $item->vehicle_type !== $item->description){{ $item->vehicle_type }}@endif
                                                @if($item->vehicle_type && $item->vehicle_type !== $item->description && $item->route)<span> · </span>@endif
                                                @if($item->route)Route: {{ $item->route }}@endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $item->service_days ?: number_format((float) $item->quantity, 2) }}</td>
                                    <td class="text-end">
                                        @if($item->vehicle_type && $item->service_days)
                                            <span aria-label="Rate not specified">—</span>
                                        @else
                                            {{ $formatMoney($item->unit_price) }}
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $formatMoney($item->discount) }}</td>
                                    <td class="text-end">{{ $formatMoney($item->taxable_amount) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->tax_rate, 1) }}%</td>
                                    <td class="text-end fw-semibold">{{ $formatMoney($item->total_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="small text-secondary mb-0">No additional itemized services are recorded.</p>
            @endif
        </section>

        <section class="invoice-summary-section row g-4 py-4 border-bottom" aria-labelledby="summary-heading">
            <div class="col-12 col-lg-5">
                <p class="invoice-label mb-1">Payment and notes</p>
                <h2 class="h6 fw-bold mb-2">Terms &amp; remittance</h2>
                <p class="small mb-3">{{ $paymentTerms }}</p>
                @if($invoice->notes)
                    <div class="invoice-note mb-3"><strong>Notes</strong><br>{{ $invoice->notes }}</div>
                @endif
                @if(is_array($bankDetails) && count($bankDetails))
                    <div class="small">
                        <strong class="d-block mb-1">Bank details</strong>
                        @foreach($bankDetails as $key => $value)
                            @if(is_scalar($value) && $value !== '')
                                <div>{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }}</div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="small text-secondary mb-1">Bank details not provided.</p>
                @endif
                @if($profile?->upi_id)<p class="small mb-0 mt-1">UPI: {{ $profile->upi_id }}</p>@endif
                @if($invoice->status === 'cancelled' && $invoice->cancellation_reason)
                    <div class="alert alert-danger py-2 px-3 small mt-3 mb-0"><strong>Cancellation reason:</strong> {{ $invoice->cancellation_reason }}</div>
                @endif
            </div>
            <div class="col-12 col-lg-7">
                <p class="invoice-label mb-1">Monthly billing and GST summary</p>
                <h2 id="summary-heading" class="h6 fw-bold mb-2">Invoice total</h2>
                <dl class="invoice-totals mb-0">
                    <div><dt>Subtotal</dt><dd>{{ $formatMoney($invoice->subtotal) }}</dd></div>
                    @if((float) $invoice->discount_total > 0)<div><dt>Discount</dt><dd>− {{ $formatMoney($invoice->discount_total) }}</dd></div>@endif
                    <div><dt>Taxable amount</dt><dd>{{ $formatMoney($invoice->taxable_amount) }}</dd></div>
                    @if((float) $invoice->cgst_amount > 0)<div><dt>CGST</dt><dd>{{ $formatMoney($invoice->cgst_amount) }}</dd></div>@endif
                    @if((float) $invoice->sgst_amount > 0)<div><dt>SGST</dt><dd>{{ $formatMoney($invoice->sgst_amount) }}</dd></div>@endif
                    @if((float) $invoice->igst_amount > 0)<div><dt>IGST</dt><dd>{{ $formatMoney($invoice->igst_amount) }}</dd></div>@endif
                    <div class="invoice-total"><dt>Total payable</dt><dd>{{ $formatMoney($invoice->total_amount) }}</dd></div>
                    <div><dt>Amount paid</dt><dd>{{ $formatMoney($invoice->amount_paid) }}</dd></div>
                    <div class="invoice-balance"><dt>Balance due</dt><dd>{{ $formatMoney($invoice->balance_due) }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="invoice-section py-4 border-bottom" aria-labelledby="payment-history-heading">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                <div>
                    <p class="invoice-label mb-1">Recorded allocations</p>
                    <h2 id="payment-history-heading" class="h6 fw-bold mb-0">Payment history</h2>
                </div>
                <span class="badge {{ $statusClass }}">{{ ucfirst($invoice->status) }}</span>
            </div>
            @if($invoice->allocations->isNotEmpty())
                <div class="table-responsive invoice-table-wrap">
                    <table class="table table-sm align-middle invoice-table invoice-payment-table mb-0">
                        <thead><tr><th>Payment #</th><th>Date</th><th>Method</th><th>Payment status</th><th class="text-end">Allocated</th></tr></thead>
                        <tbody>
                            @foreach($invoice->allocations as $allocation)
                                @php($payment = $allocation->payment)
                                <tr>
                                    <td>{{ $payment?->payment_number ?: 'Payment' }}</td>
                                    <td>{{ $payment?->payment_date?->format('M j, Y') ?? '—' }}</td>
                                    <td>{{ $payment?->payment_method ?: '—' }}</td>
                                    <td><span class="badge {{ $payment?->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($payment?->status ?? 'unavailable') }}</span></td>
                                    <td class="text-end fw-semibold">{{ $formatMoney($allocation->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="small text-secondary mb-0">No payment allocations are recorded for this invoice.</p>
            @endif
        </section>

        <footer class="pt-3 text-center">
            <p class="fw-semibold mb-2">Thank you for choosing {{ $businessName }}.</p>
            @if($invoice->footer)<p class="small text-secondary mb-2">{{ $invoice->footer }}</p>@endif
            <p class="small text-secondary mb-0">This is a computer-generated invoice.</p>
        </footer>
    </article>
</div>

@push('head')
<style>
    .invoice-detail-page {
        --invoice-navy: #153d68;
        --invoice-ink: #1b2e43;
        --invoice-muted: #64758a;
        --invoice-rule: #d6dee7;
        color: var(--invoice-ink);
        min-width: 0;
    }
    .invoice-toolbar { min-width: 0; }
    .invoice-paper {
        width: 100%;
        max-width: 1200px;
        min-width: 0;
        margin-inline: auto;
        padding: clamp(1.1rem, 2.8vw, 2.5rem);
        background: #fff;
        border: 1px solid var(--invoice-rule);
        border-top: 5px solid var(--invoice-navy);
        box-shadow: 0 12px 36px rgba(25, 48, 72, .07);
    }
    .invoice-heading {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        align-items: start;
        gap: 2rem;
        padding-bottom: 1.75rem;
        border-bottom: 1px solid var(--invoice-rule);
    }
    .invoice-label {
        color: var(--invoice-navy);
        font-size: .69rem;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
    }
    .invoice-business, .invoice-meta { min-width: 0; }
    .invoice-meta h1, .invoice-heading h2 { color: var(--invoice-navy); overflow-wrap: anywhere; }
    .invoice-parties { --bs-gutter-x: 1rem; }
    .party-block {
        height: 100%;
        padding: 1.1rem 1.2rem;
        border: 1px solid var(--invoice-rule);
        border-top: 2px solid #b8c8d8;
        background: #fbfcfe;
    }
    .invoice-facts > div, .invoice-totals > div {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: .55rem 0;
        border-bottom: 1px solid #edf0f4;
    }
    .invoice-facts dt, .invoice-totals dt { color: var(--invoice-muted); font-weight: 400; }
    .invoice-facts dd, .invoice-totals dd { margin: 0; text-align: right; font-weight: 600; }
    .invoice-facts dd { overflow-wrap: anywhere; }
    .invoice-section, .invoice-summary-section { min-width: 0; }
    .invoice-table-wrap {
        max-width: 100%;
        border: 1px solid var(--invoice-rule);
        border-radius: .2rem;
        overscroll-behavior-x: contain;
    }
    .invoice-table { min-width: 700px; margin-bottom: 0; font-size: .84rem; }
    .invoice-trip-table { min-width: 760px; }
    .invoice-items-table { min-width: 900px; }
    .invoice-payment-table { min-width: 650px; }
    .invoice-table thead th {
        padding: .7rem .75rem;
        color: var(--invoice-navy);
        background: #eef3f8;
        font-weight: 700;
        white-space: nowrap;
        border-bottom-color: #cbd7e3;
    }
    .invoice-table td { padding: .7rem .75rem; vertical-align: top; }
    .invoice-table td.text-end { white-space: nowrap; }
    .invoice-total { color: var(--invoice-navy); font-size: 1.02rem; font-weight: 700; }
    .invoice-total dt, .invoice-total dd { font-weight: 700 !important; }
    .invoice-balance { color: #a83232; font-weight: 700; }
    .invoice-balance dt, .invoice-balance dd { font-weight: 700 !important; }
    .invoice-totals { max-width: 38rem; margin-left: auto; }
    .invoice-totals > div { gap: 1.5rem; }
    .invoice-totals dd { white-space: nowrap; font-variant-numeric: tabular-nums; }
    .invoice-note { padding: .75rem; border-left: 3px solid #aec0d2; background: #f5f8fb; font-size: .875rem; }
    @media (max-width: 767.98px) {
        .invoice-heading { grid-template-columns: minmax(0, 1fr); gap: 1.25rem; }
        .invoice-meta { text-align: left !important; }
        .invoice-paper { padding: 1rem; }
        .invoice-parties, .invoice-summary-section { --bs-gutter-x: 1rem; }
        .invoice-summary-section { --bs-gutter-y: 1.5rem; }
        .invoice-totals { max-width: none; }
    }
    @media print {
        .invoice-toolbar { display: none !important; }
        .invoice-paper { max-width: none; border: 0; box-shadow: none; padding: 0; }
        .invoice-table-wrap { overflow: visible !important; border: 0; }
        .invoice-table { min-width: 0; font-size: 8pt; }
    }
</style>
@endpush
@endsection
