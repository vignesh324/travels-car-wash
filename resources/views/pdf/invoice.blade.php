<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
    .page { padding: 30px; }
    .header { display: flex; justify-content: space-between; margin-bottom: 24px; border-bottom: 2px solid #2563eb; padding-bottom: 16px; }
    .business-name { font-size: 18px; font-weight: bold; color: #1e40af; }
    .business-details { font-size: 10px; color: #555; margin-top: 4px; line-height: 1.5; }
    .invoice-title { text-align: right; }
    .invoice-title h1 { font-size: 22px; font-weight: bold; color: #1e40af; }
    .invoice-title .inv-number { font-size: 13px; font-weight: bold; margin-top: 4px; }
    .invoice-title .inv-date { font-size: 10px; color: #555; margin-top: 2px; }
    .parties { display: flex; justify-content: space-between; margin-bottom: 20px; }
    .party-box { width: 48%; }
    .party-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; letter-spacing: 0.5px; }
    .party-name { font-size: 12px; font-weight: bold; }
    .party-detail { font-size: 10px; color: #444; line-height: 1.5; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    thead th { background: #1e40af; color: white; padding: 7px 8px; text-align: left; font-size: 10px; }
    thead th.right { text-align: right; }
    tbody td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
    tbody td.right { text-align: right; }
    tbody tr:nth-child(even) { background: #f9fafb; }
    .totals { margin-left: auto; width: 260px; }
    .totals table { margin-bottom: 0; }
    .totals td { padding: 4px 8px; font-size: 10px; border: none; }
    .totals td.label { color: #555; }
    .totals td.value { text-align: right; font-weight: 500; }
    .totals .grand-total td { font-size: 13px; font-weight: bold; border-top: 2px solid #1e40af; padding-top: 6px; color: #1e40af; }
    .balance-due td { color: #dc2626; font-weight: bold; }
    .footer-section { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    .bank-details { font-size: 10px; color: #444; line-height: 1.6; }
    .footer-note { font-size: 9px; color: #888; margin-top: 8px; }
    .status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
    .status-paid { background: #dcfce7; color: #166534; }
    .status-partial { background: #fef9c3; color: #854d0e; }
    .status-issued { background: #dbeafe; color: #1e40af; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            <div class="business-name">{{ $profile?->business_name ?? 'Business Name' }}</div>
            <div class="business-details">
                @if($profile?->address){{ $profile->address }}<br>@endif
                @if($profile?->phone)Phone: {{ $profile->phone }}<br>@endif
                @if($profile?->email)Email: {{ $profile->email }}<br>@endif
                @if($profile?->gstin)GSTIN: {{ $profile->gstin }}@endif
            </div>
        </div>
        <div class="invoice-title">
            <h1>TAX INVOICE</h1>
            <div class="inv-number">{{ $invoice->invoice_number }}</div>
            <div class="inv-date">Date: {{ $invoice->invoice_date->format('d-m-Y') }}</div>
            @if($invoice->due_date)
            <div class="inv-date">Due: {{ $invoice->due_date->format('d-m-Y') }}</div>
            @endif
            <div style="margin-top:6px">
                <span class="status-badge status-{{ $invoice->status }}">{{ strtoupper($invoice->status) }}</span>
            </div>
        </div>
    </div>

    <div class="parties">
        <div class="party-box">
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $invoice->customer_name }}</div>
            <div class="party-detail">
                @if($invoice->customer_address){{ $invoice->customer_address }}<br>@endif
                @if($invoice->customer_state){{ $invoice->customer_state }}@if($invoice->customer_state_code) ({{ $invoice->customer_state_code }})@endif<br>@endif
                @if($invoice->customer_gstin)GSTIN: {{ $invoice->customer_gstin }}@endif
            </div>
        </div>
        <div class="party-box" style="text-align:right">
            <div class="party-label">Place of Supply</div>
            <div class="party-detail">{{ $invoice->place_of_supply ?? '—' }}</div>
            @if($invoice->payment_terms)
            <div class="party-label" style="margin-top:8px">Payment Terms</div>
            <div class="party-detail">{{ $invoice->payment_terms }}</div>
            @endif
        </div>
    </div>

    @php
        $hasTripSummaryItems = $invoice->items->isNotEmpty()
            && $invoice->items->every(fn ($item) => $item->vehicle_type !== null);
    @endphp
    <table>
        @if($hasTripSummaryItems)
        <thead>
            <tr>
                <th style="width:8%">Sl. No.</th>
                <th style="width:35%">Vehicle Type</th>
                <th style="width:32%">Route</th>
                <th class="right" style="width:10%">Days</th>
                <th class="right" style="width:15%">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->vehicle_type }}</td>
                <td>{{ $item->route ?: '—' }}</td>
                <td class="right">{{ $item->service_days }}</td>
                <td class="right">{{ number_format($item->total_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        @else
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:40%">Description</th>
                <th class="right" style="width:8%">Qty</th>
                <th class="right" style="width:12%">Rate (₹)</th>
                <th class="right" style="width:10%">Disc (₹)</th>
                <th class="right" style="width:12%">Taxable (₹)</th>
                <th class="right" style="width:8%">GST%</th>
                <th class="right" style="width:12%">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="right">{{ number_format($item->quantity, 2) }}</td>
                <td class="right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="right">{{ number_format($item->discount, 2) }}</td>
                <td class="right">{{ number_format($item->taxable_amount, 2) }}</td>
                <td class="right">{{ number_format($item->tax_rate, 1) }}%</td>
                <td class="right">{{ number_format($item->total_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        @endif
    </table>

    <div class="totals">
        <table>
            <tr><td class="label">Subtotal</td><td class="value">₹{{ number_format($invoice->subtotal, 2) }}</td></tr>
            @if($invoice->discount_total > 0)
            <tr><td class="label">Discount</td><td class="value">- ₹{{ number_format($invoice->discount_total, 2) }}</td></tr>
            @endif
            <tr><td class="label">Taxable Amount</td><td class="value">₹{{ number_format($invoice->taxable_amount, 2) }}</td></tr>
            @if($invoice->cgst_amount > 0)
            <tr><td class="label">CGST</td><td class="value">₹{{ number_format($invoice->cgst_amount, 2) }}</td></tr>
            <tr><td class="label">SGST</td><td class="value">₹{{ number_format($invoice->sgst_amount, 2) }}</td></tr>
            @endif
            @if($invoice->igst_amount > 0)
            <tr><td class="label">IGST</td><td class="value">₹{{ number_format($invoice->igst_amount, 2) }}</td></tr>
            @endif
            <tr class="grand-total"><td class="label">Total</td><td class="value">₹{{ number_format($invoice->total_amount, 2) }}</td></tr>
            @if($invoice->amount_paid > 0)
            <tr><td class="label">Amount Paid</td><td class="value">₹{{ number_format($invoice->amount_paid, 2) }}</td></tr>
            @endif
            @if($invoice->balance_due > 0)
            <tr class="balance-due"><td class="label">Balance Due</td><td class="value">₹{{ number_format($invoice->balance_due, 2) }}</td></tr>
            @endif
        </table>
    </div>

    @if($invoice->notes)
    <div style="margin-top:16px; font-size:10px; color:#555;">
        <strong>Notes:</strong> {{ $invoice->notes }}
    </div>
    @endif

    <div class="footer-section">
        @if($invoice->bank_details_snapshot)
        <div class="bank-details">
            <strong>Bank Details:</strong><br>
            @foreach($invoice->bank_details_snapshot as $key => $val)
            {{ ucfirst($key) }}: {{ $val }}<br>
            @endforeach
        </div>
        @endif
        @if($profile?->upi_id)
        <div class="bank-details" style="margin-top:4px">UPI: {{ $profile->upi_id }}</div>
        @endif
        @if($invoice->footer)
        <div class="footer-note">{{ $invoice->footer }}</div>
        @endif
        <div class="footer-note" style="margin-top:8px; text-align:center;">This is a computer-generated invoice.</div>
    </div>
</div>
</body>
</html>
