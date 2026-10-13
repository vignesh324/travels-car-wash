<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Invoice extends Model
{
    use HasAuditFields, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'invoice_number', 'invoice_date', 'due_date', 'customer_id', 'source',
        'customer_name', 'customer_gstin', 'customer_address', 'customer_state', 'customer_state_code',
        'business_name', 'business_gstin', 'business_address', 'bank_details_snapshot',
        'place_of_supply',
        'subtotal', 'discount_total', 'taxable_amount',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'tax_total',
        'total_amount', 'amount_paid', 'balance_due',
        'payment_terms', 'notes', 'footer',
        'status', 'cancellation_reason', 'issued_at', 'cancelled_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'bank_details_snapshot' => 'array',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function tripLinks()
    {
        return $this->hasMany(InvoiceTripLink::class);
    }

    public function trips()
    {
        return $this->belongsToMany(Trip::class, 'invoice_trip_links');
    }

    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
            ->withPivot('amount', 'notes')
            ->withTimestamps();
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Recalculate totals from items. Never trust client totals.
     */
    public static function calculateTotals(array $items, string $placeOfSupply, string $businessState): array
    {
        $subtotal = 0;
        $discountTotal = 0;
        $taxableAmount = 0;
        $cgst = 0;
        $sgst = 0;
        $igst = 0;
        $processedItems = [];

        $isInterState = strtolower(trim($placeOfSupply)) !== strtolower(trim($businessState));

        foreach ($items as $i => $item) {
            $qty = max(0, (float) ($item['quantity'] ?? 1));
            $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
            $discount = max(0, (float) ($item['discount'] ?? 0));
            $taxRate = max(0, (float) ($item['tax_rate'] ?? 0));

            $lineSubtotal = $qty * $unitPrice;
            $lineDiscount = min($discount, $lineSubtotal);
            $lineTaxable = $lineSubtotal - $lineDiscount;
            $lineTax = round($lineTaxable * $taxRate / 100, 2);

            $halfRate = $taxRate / 2;
            $lineCgst = $isInterState ? 0 : round($lineTaxable * $halfRate / 100, 2);
            $lineSgst = $isInterState ? 0 : round($lineTaxable * $halfRate / 100, 2);
            $lineIgst = $isInterState ? $lineTax : 0;

            $lineTotal = $lineTaxable + $lineTax;

            $subtotal += $lineSubtotal;
            $discountTotal += $lineDiscount;
            $taxableAmount += $lineTaxable;
            $cgst += $lineCgst;
            $sgst += $lineSgst;
            $igst += $lineIgst;

            $processedItems[] = array_merge($item, [
                'taxable_amount' => $lineTaxable,
                'cgst_rate' => $isInterState ? 0 : $halfRate,
                'sgst_rate' => $isInterState ? 0 : $halfRate,
                'igst_rate' => $isInterState ? $taxRate : 0,
                'tax_amount' => $lineTax,
                'total_amount' => $lineTotal,
                'sort_order' => $i,
            ]);
        }

        $taxTotal = $cgst + $sgst + $igst;
        $total = $taxableAmount + $taxTotal;

        return [
            'items' => $processedItems,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'taxable_amount' => round($taxableAmount, 2),
            'cgst_amount' => round($cgst, 2),
            'sgst_amount' => round($sgst, 2),
            'igst_amount' => round($igst, 2),
            'tax_total' => round($taxTotal, 2),
            'total_amount' => round($total, 2),
        ];
    }

    public function recalculatePaymentStatus(): void
    {
        $paid = $this->allocations()
            ->whereHas('payment', fn($q) => $q->where('status', 'active'))
            ->sum('amount');

        $this->amount_paid = $paid;
        $this->balance_due = max(0, $this->total_amount - $paid);

        if ($paid <= 0) {
            $this->status = $this->status === 'cancelled' ? 'cancelled' : 'issued';
        } elseif ($paid >= $this->total_amount) {
            $this->status = 'paid';
        } else {
            $this->status = 'partial';
        }

        $this->saveQuietly();
    }
}
