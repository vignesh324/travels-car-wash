<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\InvoiceNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use App\Http\Traits\AjaxDataTableTrait;

class PaymentController extends Controller
{
    use AjaxDataTableTrait;

    public function addEdit()
    {
        $pageTitle = 'Record Payment';
        $formUrl = route('payments.store');
        $customers = Customer::dropdownOptions();
        $outstandingInvoices = Invoice::outstandingDropdownOptions();
        $html = view('payments.add_edit', compact('formUrl', 'customers', 'outstandingInvoices'))->render();

        return response()->json(compact('pageTitle', 'formUrl', 'html', 'customers', 'outstandingInvoices'));
    }

    public function getData(Request $request)
    {
        $query = Payment::with(['customer:id,name,company_name,type'])
            ->when($request->customer_id,    fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->status,         fn($q, $v) => $q->where('status', $v))
            ->when($request->payment_method, fn($q, $v) => $q->where('payment_method', $v))
            ->when($request->date_from,      fn($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($request->date_to,        fn($q, $v) => $q->whereDate('payment_date', '<=', $v));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('actions', fn (Payment $payment) =>
                    '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions">'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="payments.view" aria-label="' . e('View payment ' . $payment->payment_number) . '" title="View" data-payment-uuid="' . e($payment->uuid) . '"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="payments.receipt" aria-label="' . e('Download receipt for payment ' . $payment->payment_number) . '" title="Receipt" data-receipt-url="' . e(route('payments.receipt', ['payment' => $payment->uuid])) . '"><i class="bi bi-receipt" aria-hidden="true"></i><span>Receipt</span></button>'
                    . ($payment->status === 'active'
                        ? '<button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="payments.reverse" aria-label="' . e('Reverse payment ' . $payment->payment_number) . '" title="Reverse" data-payment-uuid="' . e($payment->uuid) . '" data-payment-number="' . e($payment->payment_number) . '" data-payment-amount="' . e($payment->amount) . '"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span>Reverse</span></button>'
                        : '')
                    . '</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function index()
    {
        $customers = Customer::dropdownOptions();

        return view('payments.index', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'              => 'required|exists:customers,id',
            'payment_date'             => 'required|date',
            'amount'                   => 'required|numeric|min:0.01',
            'payment_method'           => 'required|in:Cash,UPI,Bank Transfer,Card,Other',
            'transaction_reference'    => 'nullable|string|max:255',
            'notes'                    => 'nullable|string',
            'allocations'              => 'nullable|array',
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
                'status'             => 'reversed',
                'reversal_reason'    => $request->reason,
                'reversed_at'        => now(),
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
        $profile  = BusinessProfile::first();
        $filename = 'Receipt-' . str_replace('/', '-', $payment->payment_number) . '.pdf';

        return Pdf::loadView('pdf.receipt', compact('payment', 'profile'))->download($filename);
    }

    public function receiptSignedUrl(Payment $payment)
    {
        $url = URL::temporarySignedRoute('pdf.receipt', now()->addMinutes(5), ['payment' => $payment->id]);

        return response()->json(['url' => $url]);
    }

    public function update(Request $request, Payment $payment)
    {
        return response()->json(['message' => 'Use reverse to void a payment.'], 422);
    }

    public function destroy(Payment $payment)
    {
        return response()->json(['message' => 'Payments cannot be deleted. Use reverse instead.'], 422);
    }

    public function receiptSigned(Payment $payment)
    {
        $payment->load(['customer', 'allocations.invoice']);
        $profile  = BusinessProfile::first();
        $filename = 'Receipt-' . str_replace('/', '-', $payment->payment_number) . '.pdf';

        return Pdf::loadView('pdf.receipt', compact('payment', 'profile'))->download($filename);
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

            $amount = min((float) $alloc['amount'], $invoice->balance_due);
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
