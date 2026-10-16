<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
.page { padding: 30px; max-width: 600px; margin: 0 auto; }
.header { text-align: center; border-bottom: 2px solid #0891b2; padding-bottom: 16px; margin-bottom: 20px; }
.business-name { font-size: 16px; font-weight: bold; color: #0e7490; }
.business-details { font-size: 10px; color: #555; margin-top: 4px; line-height: 1.5; }
.bill-title { font-size: 20px; font-weight: bold; color: #0e7490; margin-top: 8px; }
.bill-number { font-size: 12px; color: #555; margin-top: 4px; }
.info-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
.info-row .label { color: #6b7280; }
.info-row .value { font-weight: 500; }
table { width: 100%; border-collapse: collapse; margin: 16px 0; }
thead th { background: #0e7490; color: white; padding: 7px 8px; text-align: left; font-size: 10px; }
thead th.right { text-align: right; }
tbody td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
tbody td.right { text-align: right; }
tbody tr:nth-child(even) { background: #f9fafb; }
.totals { margin-left: auto; width: 240px; }
.totals table { margin: 0; }
.totals td { padding: 4px 8px; font-size: 10px; border: none; }
.totals td.label { color: #555; }
.totals td.value { text-align: right; font-weight: 500; }
.totals .grand-total td { font-size: 13px; font-weight: bold; border-top: 2px solid #0e7490; padding-top: 6px; color: #0e7490; }
.balance-due td { color: #dc2626; font-weight: bold; }
.footer { margin-top: 20px; text-align: center; font-size: 9px; color: #9ca3af; }
.status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
.status-paid { background: #dcfce7; color: #166534; }
.status-partial { background: #fef9c3; color: #854d0e; }
.status-unpaid { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="business-name">{{ $profile?->business_name ?? 'Business Name' }}</div>
        <div class="business-details">
            @if($profile?->address){{ $profile->address }}<br>@endif
            @if($profile?->phone)Phone: {{ $profile->phone }}<br>@endif
            @if($profile?->gstin)GSTIN: {{ $profile->gstin }}@endif
        </div>
        <div class="bill-title">CAR WASH BILL</div>
        <div class="bill-number">{{ $carWashTransaction->transaction_number }}</div>
    </div>

    <div class="info-row">
        <span class="label">Date</span>
        <span class="value">{{ \Carbon\Carbon::parse($carWashTransaction->service_datetime)->format('d-m-Y H:i') }}</span>
    </div>
    @if($carWashTransaction->customer)
    <div class="info-row">
        <span class="label">Customer</span>
        <span class="value">{{ $carWashTransaction->customer->company_name ?? $carWashTransaction->customer->name }}</span>
    </div>
    @endif
    @if($carWashTransaction->vehicle_registration)
    <div class="info-row">
        <span class="label">Vehicle</span>
        <span class="value">{{ $carWashTransaction->vehicle_registration }}</span>
    </div>
    @endif
    <div class="info-row">
        <span class="label">Payment Mode</span>
        <span class="value">{{ $carWashTransaction->payment_mode ?? '—' }}</span>
    </div>
    <div class="info-row">
        <span class="label">Status</span>
        <span class="value">
            <span class="status-badge status-{{ $carWashTransaction->payment_status }}">{{ strtoupper($carWashTransaction->payment_status) }}</span>
        </span>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:45%">Service</th>
                <th class="right" style="width:10%">Qty</th>
                <th class="right" style="width:15%">Rate (₹)</th>
                <th class="right" style="width:10%">GST%</th>
                <th class="right" style="width:15%">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($carWashTransaction->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->service_name }}</td>
                <td class="right">{{ number_format($item->quantity, 2) }}</td>
                <td class="right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="right">{{ number_format($item->tax_rate, 1) }}%</td>
                <td class="right">{{ number_format($item->total_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td class="label">Subtotal</td><td class="value">₹{{ number_format($carWashTransaction->subtotal, 2) }}</td></tr>
            @if($carWashTransaction->discount > 0)
            <tr><td class="label">Discount</td><td class="value">- ₹{{ number_format($carWashTransaction->discount, 2) }}</td></tr>
            @endif
            @if($carWashTransaction->tax_amount > 0)
            <tr><td class="label">Tax</td><td class="value">₹{{ number_format($carWashTransaction->tax_amount, 2) }}</td></tr>
            @endif
            <tr class="grand-total"><td class="label">Total</td><td class="value">₹{{ number_format($carWashTransaction->total_amount, 2) }}</td></tr>
            @if($carWashTransaction->amount_paid > 0)
            <tr><td class="label">Amount Paid</td><td class="value">₹{{ number_format($carWashTransaction->amount_paid, 2) }}</td></tr>
            @endif
            @if($carWashTransaction->balance_due > 0)
            <tr class="balance-due"><td class="label">Balance Due</td><td class="value">₹{{ number_format($carWashTransaction->balance_due, 2) }}</td></tr>
            @endif
        </table>
    </div>

    @if($carWashTransaction->notes)
    <div style="margin-top:16px; font-size:10px; color:#555;">Notes: {{ $carWashTransaction->notes }}</div>
    @endif

    <div class="footer">Thank you for your business.<br>This is a computer-generated bill.</div>
</div>
</body>
</html>
