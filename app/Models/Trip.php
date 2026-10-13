<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Trip extends Model
{
    use HasAuditFields, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'trip_number', 'trip_date', 'vehicle_id', 'customer_id', 'trip_type',
        'pickup_location', 'drop_location', 'description',
        'start_odometer', 'end_odometer', 'distance_km', 'driver_name',
        'fare_amount', 'additional_charges', 'discount',
        'is_taxable', 'tax_rate', 'taxable_amount', 'tax_amount', 'total_amount',
        'payment_mode', 'amount_received', 'balance_due',
        'status', 'payment_status', 'is_invoiced', 'notes',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'fare_amount' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'discount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_invoiced' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Calculate and set all financial fields from base inputs.
     * Never trust client-calculated totals.
     */
    public static function calculateAmounts(array $data): array
    {
        $fare = max(0, (float) ($data['fare_amount'] ?? 0));
        $additional = max(0, (float) ($data['additional_charges'] ?? 0));
        $discount = max(0, (float) ($data['discount'] ?? 0));
        $isTaxable = (bool) ($data['is_taxable'] ?? false);
        $taxRate = $isTaxable ? max(0, (float) ($data['tax_rate'] ?? 0)) : 0;

        $subtotal = $fare + $additional;
        $discount = min($discount, $subtotal);
        $taxableAmount = $subtotal - $discount;
        $taxAmount = $isTaxable ? round($taxableAmount * $taxRate / 100, 2) : 0;
        $total = $taxableAmount + $taxAmount;
        $received = max(0, (float) ($data['amount_received'] ?? 0));
        $balance = max(0, $total - $received);

        $paymentStatus = 'unpaid';
        if ($received >= $total && $total > 0) {
            $paymentStatus = 'paid';
        } elseif ($received > 0) {
            $paymentStatus = 'partial';
        }

        return array_merge($data, [
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'balance_due' => $balance,
            'payment_status' => $paymentStatus,
            'tax_rate' => $taxRate,
            'discount' => $discount,
        ]);
    }
}
