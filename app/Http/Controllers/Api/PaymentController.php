<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\InvoiceNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('datatable')) {
            $query = Payment::with(['customer:id,name,company_name,type'])
                ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
                ->when($request->status, fn($q, $v) => $q->where('status', $v))
                ->when($request->payment_method, fn($q, $v) => $q->where('payment_method', $v))
                ->when($request->date_from, fn($q, $v) => $q->whereDate('payment_date', '>=', $v))
                ->when($request->date_to, fn($q, $v) => $q->whereDate('payment_date', '<=', $v));

            return DataTables::of($query)->make(true);
        }

        return response()->json([]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'           => 'required|exists:customers,id',
            'payment_date'          => 'required|date',
            'amount'                => 'required|numeric|min:0.01',
            'payment_method'        => 'required|in:Cash,UPI,Bank Transfer,Card,Other',
            'transaction_reference' => 'nullable|string|max:255',
            'notes'                 => 'nullable|string',
            'allocations'           => 'nullable|array',
            'allocations.*.invoice_id' => 'required|exists:invoices,id',
            'allocations.*.amount'     => 'required|numeric|min:0.01',
        ]);

        $payment = DB::transaction(function () use ($validated) {
            $number = app(InvoiceNumberService::class)->generateReceipt();

            $payment = Payment::create([
                'payment_number'        => $number,
                'customer_id'           => $validated['customer_id'],
                'payment_date'          => $validated['payment_date'],
                'amount'                => $validated['amount'],
                'allocated_amount'      => 0,
                'unallocated_amount'    => $validated['amount'],
                'payment_method'        => $validated['payment_method'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'notes'                 => $validated['notes'] ?? null,
                'status'                => 'active',
            ]);

            if (!empty($validated['allocations'])) {
                $this->applyAllocations($payment, $validated['allocations']);
            }

            activity()->causedBy(Auth::user())->performedOn($payment)
                ->withProperties(['amount' => $payment->amount, 'method' => $payment->payment_method])
                ->log('Payment recorded');

            return $payment;
        });

        return response()->json($payment->load(['customer:id,name,company_name', 'allocations.invoice']), 201);
    }

    public function show(Payment $payment)
    {
        return response()->json($payment->load(['customer', 'allocations.invoice']));
    }

    public function allocate(Request $request, Payment $payment)
    {
        if ($payment->status !== 'active') {
            return response()->json(['message' => 'Cannot allocate a reversed or refunded payment.'], 422);
        }

        $request->validate([
            'allocations'              => 'required|array|min:1',
            'allocations.*.invoice_id' => 'required|exists:invoices,id',
            'allocations.*.amount'     => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () use ($payment, $request) {
            $this->applyAllocations($payment, $request->allocations);
        });

        return response()->json($payment->fresh()->load(['allocations.invoice']));
    }

    public function reverse(Request $request, Payment $payment)
    {
        if ($payment->status !== 'active') {
            return response()->json(['message' => 'Payment already reversed.'], 422);
        }

        $request->validate(['reason' => 'required|string|max:500']);

        DB::transaction(function () use ($payment, $request) {
            $invoiceIds = $payment->allocations()->pluck('invoice_id');

            $payment->allocations()->delete();
            $payment->update([
                'status'          => 'reversed',
                'reversal_reason' => $request->reason,
                'reversed_at'     => now(),
                'allocated_amount'   => 0,
                'unallocated_amount' => $payment->amount,
            ]);

            foreach ($invoiceIds as $invoiceId) {
                Invoice::find($invoiceId)?->recalculatePaymentStatus();
            }

            activity()->causedBy(Auth::user())->performedOn($payment)
                ->withProperties(['reason' => $request->reason])
                ->log('Payment reversed');
        });

        return response()->json(['message' => 'Payment reversed']);
    }

    public function receipt(Payment $payment)
    {
        $payment->load(['customer', 'allocations.invoice']);
        $profile = BusinessProfile::first();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', compact('payment', 'profile'));
        $filename = 'Receipt-' . $payment->payment_number . '.pdf';

        return $pdf->download(str_replace('/', '-', $filename));
    }

    public function receiptSignedUrl(Payment $payment)
    {
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'pdf.receipt', now()->addMinutes(5), ['payment' => $payment->id]
        );
        return response()->json(['url' => $url]);
    }

    public function receiptSigned(Payment $payment)
    {
        $payment->load(['customer', 'allocations.invoice']);
        $profile = BusinessProfile::first();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', compact('payment', 'profile'));
        $filename = 'Receipt-' . str_replace('/', '-', $payment->payment_number) . '.pdf';

        return $pdf->download($filename);
    }

    private function applyAllocations(Payment $payment, array $allocations): void
    {
        $totalRequested = collect($allocations)->sum('amount');

        if ($totalRequested > $payment->unallocated_amount + 0.001) {
            abort(422, 'Allocation amount exceeds available payment balance.');
        }

        foreach ($allocations as $alloc) {
            $invoice = Invoice::findOrFail($alloc['invoice_id']);

            if ($invoice->customer_id !== $payment->customer_id) {
                abort(422, 'Invoice does not belong to this customer.');
            }
            if ($invoice->status === 'cancelled') {
                abort(422, 'Cannot allocate to a cancelled invoice.');
            }

            $maxAlloc = $invoice->balance_due;
            $amount = min((float) $alloc['amount'], $maxAlloc);

            if ($amount <= 0) continue;

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount'     => $amount,
                'notes'      => $alloc['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $invoice->recalculatePaymentStatus();
        }

        $payment->recalculateAllocated();
    }
}
