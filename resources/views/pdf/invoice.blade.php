<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: A4; margin: 18mm 15mm 17mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #182b42; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.45; }
    .page { width: 100%; }
    .top-rule { height: 5px; background: #153d68; margin-bottom: 16px; }
    .header-table, .party-table, .summary-table, .data-table, .totals-table, .footer-table { width: 100%; border-collapse: collapse; }
    .header-table { margin-bottom: 14px; }
    .brand { color: #153d68; font-size: 20px; font-weight: bold; letter-spacing: .3px; }
    .provider-detail { color: #536579; font-size: 8px; line-height: 1.5; }
    .invoice-heading { color: #153d68; font-size: 16px; font-weight: bold; letter-spacing: .5px; text-align: right; }
    .invoice-meta { color: #536579; font-size: 8px; text-align: right; }
    .invoice-number { color: #182b42; font-size: 11px; font-weight: bold; text-align: right; }
    .status { color: #153d68; font-size: 8px; font-weight: bold; text-align: right; text-transform: uppercase; }
    .rule { border-bottom: 1px solid #cbd5df; }
    .section-title { margin: 15px 0 6px; color: #153d68; font-size: 9px; font-weight: bold; letter-spacing: .7px; text-transform: uppercase; }
    .party-table { margin: 14px 0 15px; }
    .party-cell { width: 50%; vertical-align: top; padding: 10px 12px; border: 1px solid #d5dee7; }
    .party-cell + .party-cell { border-left: 0; }
    .label { color: #607286; font-size: 7px; font-weight: bold; letter-spacing: .7px; text-transform: uppercase; }
    .party-name { margin: 3px 0; color: #182b42; font-size: 11px; font-weight: bold; }
    .muted { color: #536579; }
    .summary-table { margin: 2px 0 15px; }
    .summary-cell { width: 25%; padding: 8px 9px; vertical-align: top; background: #f2f6fa; border-right: 2px solid #fff; }
    .summary-cell:last-child { border-right: 0; }
    .summary-value { margin-top: 3px; color: #153d68; font-size: 12px; font-weight: bold; }
    .summary-note { margin-top: 3px; color: #536579; font-size: 7px; }
    .data-table { margin: 4px 0 13px; }
    .data-table thead { display: table-header-group; }
    .data-table th { padding: 7px 6px; color: #fff; background: #153d68; border: 1px solid #153d68; text-align: left; font-size: 7px; letter-spacing: .2px; text-transform: uppercase; }
    .data-table td { padding: 6px; border-bottom: 1px solid #dce3ea; vertical-align: top; }
    .data-table tbody tr:nth-child(even) { background: #f7f9fb; }
    .right { text-align: right; }
    .center { text-align: center; }
    .numeric { text-align: right; white-space: nowrap; }
    .trip-main { color: #182b42; font-weight: bold; }
    .trip-sub { margin-top: 2px; color: #536579; font-size: 7px; }
    .subheading { color: #153d68; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; }
    .billing-area { margin-top: 12px; }
    .billing-left { width: 56%; vertical-align: top; padding-right: 14px; }
    .billing-right { width: 44%; vertical-align: top; }
    .totals-table { margin-top: 3px; }
    .totals-table td { padding: 3px 5px; border-bottom: 1px solid #e3e8ee; }
    .totals-table .total td { padding-top: 7px; color: #153d68; border-top: 2px solid #153d68; border-bottom: 0; font-size: 11px; font-weight: bold; }
    .totals-table .balance td { color: #9b3535; font-weight: bold; }
    .payment-box { margin-top: 10px; padding: 8px 10px; background: #f2f6fa; }
    .payment-box p { margin: 3px 0 0; }
    .bank-line { color: #43566b; font-size: 8px; }
    .notes { margin-top: 10px; padding: 8px 10px; border-left: 3px solid #c49a52; background: #fbf8f0; }
    .footer-table { margin-top: 18px; page-break-inside: avoid; }
    .thank-you { color: #153d68; font-size: 10px; font-weight: bold; }
    .signature-cell { width: 50%; padding: 24px 10px 0; vertical-align: bottom; }
    .signature-line { padding-top: 5px; border-top: 1px solid #8999a9; color: #536579; text-align: center; font-size: 7px; }
    .footer-note { margin-top: 11px; padding-top: 6px; border-top: 1px solid #d5dee7; color: #536579; font-size: 7px; text-align: center; }
    .empty-note { padding: 12px; color: #536579; border: 1px solid #dce3ea; text-align: center; }
    .keep-together { page-break-inside: avoid; }
</style>
</head>
<body>
@php
    $trips = $invoice->trips ?? collect();
    $quickTrips = $invoice->quickTripExecutions ?? collect();
    $tripTypes = $trips->groupBy(fn ($trip) => trim((string) $trip->trip_type) ?: 'Unspecified');
    $tripAmount = $trips->sum(fn ($trip) => (float) $trip->total_amount);
    $serviceDates = $trips->pluck('trip_date')->merge($quickTrips->pluck('trip_date'))->filter();
    $firstTripDate = $serviceDates->min();
    $lastTripDate = $serviceDates->max();
    $billingPeriod = $firstTripDate && $lastTripDate
        ? ($firstTripDate->isSameDay($lastTripDate) ? $firstTripDate->format('d-m-Y') : $firstTripDate->format('d-m-Y') . ' to ' . $lastTripDate->format('d-m-Y'))
        : 'Service period not recorded';
    $manualItems = $trips->isNotEmpty()
        ? $invoice->items->filter(fn ($item) => $item->vehicle_type === null)
        : $invoice->items;
    $businessName = $invoice->business_name ?: $profile?->business_name ?: 'ADU Travels';
    $businessAddress = $invoice->business_address ?: $profile?->address;
    $businessGstin = $invoice->business_gstin ?: $profile?->gstin;
@endphp
<div class="page">
    <div class="top-rule"></div>
    <table class="header-table">
        <tr>
            <td style="width:55%; vertical-align:top">
                <div class="brand">{{ $businessName }}</div>
                <div class="provider-detail">
                    @if($businessAddress){{ $businessAddress }}<br>@endif
                    @if($profile?->phone)Phone: {{ $profile->phone }}<br>@endif
                    @if($profile?->email)Email: {{ $profile->email }}<br>@endif
                    @if($businessGstin)GSTIN: {{ $businessGstin }}@endif
                </div>
            </td>
            <td style="width:45%; vertical-align:top">
                <div class="invoice-heading">{{ $trips->isNotEmpty() || $quickTrips->isNotEmpty() ? 'TRAVEL SERVICES INVOICE' : 'TAX INVOICE' }}</div>
                <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                <div class="invoice-meta">Invoice date: {{ $invoice->invoice_date?->format('d-m-Y') ?? '—' }}</div>
                <div class="invoice-meta">Billing period: {{ $billingPeriod }}</div>
                @if($invoice->due_date)<div class="invoice-meta">Due date: {{ $invoice->due_date->format('d-m-Y') }}</div>@endif
                <div class="status">Status: {{ strtoupper($invoice->status ?? 'issued') }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="party-table">
        <tr>
            <td class="party-cell">
                <div class="label">Bill to</div>
                <div class="party-name">{{ $invoice->customer_name ?: $invoice->customer?->company_name ?: $invoice->customer?->name ?: 'Customer' }}</div>
                <div class="muted">
                    @if($invoice->customer_address){{ $invoice->customer_address }}<br>@endif
                    @if($invoice->customer_state){{ $invoice->customer_state }}@if($invoice->customer_state_code) ({{ $invoice->customer_state_code }})@endif<br>@endif
                    @if($invoice->customer_gstin)GSTIN: {{ $invoice->customer_gstin }}@endif
                </div>
            </td>
            <td class="party-cell">
                <div class="label">Service provider</div>
                <div class="party-name">{{ $businessName }}</div>
                <div class="muted">
                    @if($businessAddress){{ $businessAddress }}<br>@endif
                    @if($businessGstin)GSTIN: {{ $businessGstin }}<br>@endif
                    Place of supply: {{ $invoice->place_of_supply ?: '—' }}
                </div>
            </td>
        </tr>
    </table>

    @if($quickTrips->isNotEmpty())
        <div class="section-title">Quick Trip execution statement</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:10%">Service date</th>
                    <th style="width:12%">Vehicle</th>
                    <th style="width:12%">Driver</th>
                    <th style="width:18%">Quick Trip</th>
                    <th style="width:15%">Pickup location</th>
                    <th style="width:15%">Drop location</th>
                    <th class="right" style="width:6%">Pickup</th>
                    <th class="right" style="width:6%">Drop</th>
                    <th class="right" style="width:8%">Last-tier rate (₹)</th>
                    <th class="right" style="width:8%">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quickTrips as $execution)
                    @php
                        $rate = (float) ($execution->per_trip_charge ?? $execution->configuration?->per_trip_charge ?? 0);
                        $amount = $execution->amount();
                        $vehicleName = $execution->vehicle?->registration_number ?: $execution->vehicle?->name ?: $execution->vehicle_name ?: 'Vehicle not recorded';
                        $driverName = $execution->driver_name ?: '—';
                    @endphp
                    <tr>
                        <td>{{ $execution->trip_date?->format('d-m-Y') ?? '—' }}</td>
                        <td>{{ $vehicleName }}</td>
                        <td>{{ $driverName }}</td>
                        <td>{{ $execution->configuration?->name ?? 'Quick Trip' }}</td>
                        <td>{{ $execution->configuration?->pickup_location ?? '—' }}</td>
                        <td>{{ $execution->configuration?->drop_location ?? '—' }}</td>
                        <td class="numeric">{{ $execution->pickup_count }}</td>
                        <td class="numeric">{{ $execution->drop_count }}</td>
                        <td class="numeric">{{ number_format($rate, 2) }}</td>
                        <td class="numeric">{{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($trips->isNotEmpty())
        <div class="section-title">Trip summary</div>
        <table class="summary-table">
            <tr>
                <td class="summary-cell">
                    <div class="label">Total billable trip records</div>
                    <div class="summary-value">{{ $trips->count() }}</div>
                </td>
                <td class="summary-cell">
                    <div class="label">Recorded trip types / counts</div>
                    <div class="summary-note">
                        @foreach($tripTypes as $type => $typeTrips)
                            <div>{{ $type }}: {{ $typeTrips->count() }}</div>
                        @endforeach
                    </div>
                </td>
                <td class="summary-cell">
                    <div class="label">Total linked trip amount</div>
                    <div class="summary-value">₹{{ number_format($tripAmount, 2) }}</div>
                </td>
                <td class="summary-cell">
                    <div class="label">Billing period</div>
                    <div class="summary-value" style="font-size:9px">{{ $billingPeriod }}</div>
                </td>
            </tr>
        </table>
        <div class="section-title">Daily trip statement</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:12%">Service date</th>
                    <th style="width:14%">Vehicle</th>
                    <th style="width:15%">Driver</th>
                    <th style="width:32%">Trip type / journey</th>
                    <th class="center" style="width:6%">Trips</th>
                    <th class="right" style="width:21%">Trip amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($trips->sortBy(fn ($trip) => $trip->trip_date?->format('Y-m-d') . '|' . $trip->trip_number) as $trip)
                <tr>
                    <td>{{ $trip->trip_date?->format('d-m-Y') ?? '—' }}</td>
                    <td>
                        <div class="trip-main">{{ $trip->vehicle?->registration_number ?: $trip->vehicle?->name ?: 'Vehicle not recorded' }}</div>
                        @if($trip->vehicle?->type)<div class="trip-sub">{{ $trip->vehicle->type }}</div>@endif
                    </td>
                    <td>{{ $trip->driver_name ?: '—' }}</td>
                    <td>
                        <div class="trip-main">{{ trim((string) $trip->trip_type) ?: 'Unspecified' }}</div>
                        @if($trip->pickup_location || $trip->drop_location)
                            <div class="trip-sub">{{ $trip->pickup_location ?: '—' }} → {{ $trip->drop_location ?: '—' }}</div>
                        @elseif($trip->route)
                            <div class="trip-sub">{{ $trip->route }}</div>
                        @elseif($trip->description)
                            <div class="trip-sub">{{ $trip->description }}</div>
                        @endif
                    </td>
                    <td class="center">1</td>
                    <td class="numeric">₹{{ number_format((float) $trip->total_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($manualItems->isNotEmpty())
            <div class="section-title">Other billed services</div>
            <table class="data-table">
                <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Rate (₹)</th><th class="right">Discount (₹)</th><th class="right">GST</th><th class="right">Amount (₹)</th></tr></thead>
                <tbody>
                    @foreach($manualItems as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="numeric">{{ number_format((float) $item->quantity, 2) }}</td>
                        <td class="numeric">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="numeric">{{ number_format((float) $item->discount, 2) }}</td>
                        <td class="numeric">{{ number_format((float) $item->tax_rate, 1) }}%</td>
                        <td class="numeric">{{ number_format((float) $item->total_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @else
        <div class="section-title">Invoice items</div>
        @if($invoice->items->isNotEmpty())
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:31%">Description</th>
                        <th class="right" style="width:8%">Qty</th>
                        <th class="right" style="width:12%">Rate (₹)</th>
                        <th class="right" style="width:11%">Discount (₹)</th>
                        <th class="right" style="width:13%">Taxable (₹)</th>
                        <th class="right" style="width:8%">GST</th>
                        <th class="right" style="width:17%">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                    <tr>
                        <td>
                            <div class="trip-main">{{ $item->vehicle_type ?: $item->description }}</div>
                            @if($item->route)<div class="trip-sub">Route: {{ $item->route }}</div>@endif
                        </td>
                        <td class="numeric">{{ number_format((float) ($item->service_days ?: $item->quantity), 2) }}</td>
                        <td class="numeric">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="numeric">{{ number_format((float) $item->discount, 2) }}</td>
                        <td class="numeric">{{ number_format((float) $item->taxable_amount, 2) }}</td>
                        <td class="numeric">{{ number_format((float) $item->tax_rate, 1) }}%</td>
                        <td class="numeric">{{ number_format((float) $item->total_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-note">No invoice line items are available.</div>
        @endif
    @endif

    <section class="billing-summary">
    <div class="section-title">Monthly billing and GST summary</div>
    <table class="header-table billing-area keep-together">
        <tr>
            <td class="billing-left">
                <div class="subheading">Payment terms</div>
                <div class="muted">{{ $invoice->payment_terms ?: $profile?->default_payment_terms ?: 'As agreed with the customer.' }}</div>
                @if($invoice->notes)
                    <div class="notes"><strong>Notes</strong><br>{{ $invoice->notes }}</div>
                @endif
                <div class="payment-box">
                    <div class="subheading">Bank / UPI details</div>
                    @if(is_array($invoice->bank_details_snapshot) && count($invoice->bank_details_snapshot))
                        @foreach($invoice->bank_details_snapshot as $key => $value)
                            @if(is_scalar($value) && $value !== '')
                                <div class="bank-line">{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }}</div>
                            @endif
                        @endforeach
                    @else
                        <div class="bank-line">Bank details not provided.</div>
                    @endif
                    @if($profile?->upi_id)<div class="bank-line">UPI: {{ $profile->upi_id }}</div>@endif
                </div>
            </td>
            <td class="billing-right">
                <table class="totals-table">
                    <tr><td class="muted">Subtotal</td><td class="numeric">₹{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
                    @if((float) $invoice->discount_total > 0)
                    <tr><td class="muted">Discount</td><td class="numeric">− ₹{{ number_format((float) $invoice->discount_total, 2) }}</td></tr>
                    @endif
                    <tr><td class="muted">Taxable amount</td><td class="numeric">₹{{ number_format((float) $invoice->taxable_amount, 2) }}</td></tr>
                    @if((float) $invoice->cgst_amount > 0)<tr><td class="muted">CGST</td><td class="numeric">₹{{ number_format((float) $invoice->cgst_amount, 2) }}</td></tr>@endif
                    @if((float) $invoice->sgst_amount > 0)<tr><td class="muted">SGST</td><td class="numeric">₹{{ number_format((float) $invoice->sgst_amount, 2) }}</td></tr>@endif
                    @if((float) $invoice->igst_amount > 0)<tr><td class="muted">IGST</td><td class="numeric">₹{{ number_format((float) $invoice->igst_amount, 2) }}</td></tr>@endif
                    <tr class="total"><td>Total payable</td><td class="numeric">₹{{ number_format((float) $invoice->total_amount, 2) }}</td></tr>
                    @if((float) $invoice->amount_paid > 0)<tr><td class="muted">Amount paid</td><td class="numeric">₹{{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>@endif
                    <tr class="balance"><td>Balance due</td><td class="numeric">₹{{ number_format((float) $invoice->balance_due, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
    </section>

    <table class="footer-table">
        <tr><td colspan="2" class="thank-you">Thank you for choosing {{ $businessName }}.</td></tr>
        <tr>
            <td class="signature-cell"><div class="signature-line">Authorized signatory</div></td>
            <td class="signature-cell"><div class="signature-line">Signature / seal</div></td>
        </tr>
    </table>
    @if($invoice->footer)<div class="footer-note">{{ $invoice->footer }}</div>@endif
    <div class="footer-note">This is a computer-generated invoice.</div>
</div>
</body>
</html>
