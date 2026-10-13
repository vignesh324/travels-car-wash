<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
.page { padding: 30px; max-width: 500px; margin: 0 auto; }
.header { text-align: center; border-bottom: 2px solid #16a34a; padding-bottom: 16px; margin-bottom: 20px; }
.business-name { font-size: 16px; font-weight: bold; color: #15803d; }
.receipt-title { font-size: 20px; font-weight: bold; color: #15803d; margin-top: 8px; }
.receipt-number { font-size: 12px; color: #555; margin-top: 4px; }
.row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
.row .label { color: #6b7280; }
.row .value { font-weight: 500; }
.amount-row { padding: 10px 0; margin-top: 8px; border-top: 2px solid #16a34a; border-bottom: 2px solid #16a34a; }
.amount-row .label { font-size: 13px; font-weight: bold; color: #15803d; }
.amount-row .value { font-size: 18px; font-weight: bold; color: #15803d; }
.alloc-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #6b7280; margin: 12px 0 4px; }
.alloc-row { display: flex; justify-content: space-between; font-size: 10px; padding: 3px 0; border-bottom: 1px solid #f3f4f6; }
.footer { margin-top: 20px; text-align: center; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="business-name">{{ $profile?->business_name ?? 'Business Name' }}</div>
        <div class="receipt-title">PAYMENT RECEIPT</div>
        <div class="receipt-number">{{ $payment->payment_number }}</div>
    </div>

    <div class="row"><span class="label">Date</span><span class="value">{{ $payment->payment_date->format('d-m-Y') }}</span></div>
    <div class="row"><span class="label">Received From</span><span class="value">{{ $payment->customer->company_name ?? $payment->customer->name }}</span></div>
    <div class="row"><span class="label">Payment Method</span><span class="value">{{ $payment->payment_method }}</span></div>
    @if($payment->transaction_reference)
    <div class="row"><span class="label">Reference</span><span class="value">{{ $payment->transaction_reference }}</span></div>
    @endif

    <div class="row amount-row">
        <span class="label">Amount Received</span>
        <span class="value">&#8377;{{ number_format($payment->amount, 2) }}</span>
    </div>

    @if($payment->allocations->isNotEmpty())
    <div class="alloc-title">Applied Against</div>
    @foreach($payment->allocations as $alloc)
    <div class="alloc-row">
        <span>{{ $alloc->invoice->invoice_number ?? 'Invoice' }}</span>
        <span>&#8377;{{ number_format($alloc->amount, 2) }}</span>
    </div>
    @endforeach
    @if($payment->unallocated_amount > 0)
    <div class="alloc-row" style="color:#d97706">
        <span>Advance / Unallocated</span>
        <span>&#8377;{{ number_format($payment->unallocated_amount, 2) }}</span>
    </div>
    @endif
    @endif

    @if($payment->notes)
    <div style="margin-top:12px;font-size:10px;color:#555">Notes: {{ $payment->notes }}</div>
    @endif

    <div class="footer">Thank you for your payment.<br>This is a computer-generated receipt.</div>
</div>
</body>
</html>
