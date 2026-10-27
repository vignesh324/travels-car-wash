<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Collection;

class TripPaymentAllocationService
{
    public function allocateInitialPayments(Invoice $invoice, Collection $trips): void
    {
        foreach ($trips as $trip) {
            $amount = round((float) $trip->amount_received, 2);
            if ($amount <= 0) {
                continue;
            }

            $payment = Payment::create([
                'payment_number' => app(InvoiceNumberService::class)->generateReceipt(),
                'customer_id' => $invoice->customer_id,
                'payment_date' => $trip->trip_date,
                'amount' => $amount,
                'allocated_amount' => 0,
                'unallocated_amount' => $amount,
                'payment_method' => $trip->payment_mode ?: 'Cash',
                'notes' => "Initial payment received for trip {$trip->trip_number}.",
                'status' => 'active',
            ]);

            $allocated = min($amount, (float) $invoice->balance_due);
            if ($allocated <= 0) {
                continue;
            }

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $allocated,
                'notes' => "Initial payment for trip {$trip->trip_number}.",
            ]);

            $payment->recalculateAllocated();
            $invoice->recalculatePaymentStatus();
        }
    }
}
