<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CarWashTransaction extends Model
{
    use HasAuditFields, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'transaction_number', 'service_datetime', 'customer_id', 'vehicle_registration',
        'subtotal', 'discount', 'taxable_amount', 'tax_amount', 'total_amount',
        'amount_paid', 'balance_due', 'payment_mode', 'payment_status',
        'invoice_id', 'notes',
    ];

    protected $casts = [
        'service_datetime' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
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
        return $this->hasMany(CarWashTransactionItem::class, 'transaction_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public static function calculateTotals(array $items, float $txDiscount = 0): array
    {
        $subtotal = 0;
        $taxAmount = 0;
        $processed = [];

        foreach ($items as $item) {
            $qty = max(0, (float) ($item['quantity'] ?? 1));
            $price = max(0, (float) ($item['unit_price'] ?? 0));
            $disc = max(0, (float) ($item['discount'] ?? 0));
            $rate = max(0, (float) ($item['tax_rate'] ?? 0));

            $lineSubtotal = $qty * $price;
            $lineDiscount = min($disc, $lineSubtotal);
            $lineTaxable = $lineSubtotal - $lineDiscount;
            $lineTax = round($lineTaxable * $rate / 100, 2);

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;

            $processed[] = array_merge($item, [
                'taxable_amount' => $lineTaxable,
                'tax_amount' => $lineTax,
                'total_amount' => $lineTaxable + $lineTax,
            ]);
        }

        $txDiscount = min($txDiscount, $subtotal);
        $taxableAmount = $subtotal - $txDiscount;
        $total = $taxableAmount + $taxAmount;

        return [
            'items' => $processed,
            'subtotal' => round($subtotal, 2),
            'discount' => round($txDiscount, 2),
            'taxable_amount' => round($taxableAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($total, 2),
        ];
    }
}
