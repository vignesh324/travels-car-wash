<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class QuickTripExecution extends Model
{
    use HasAuditFields, HasUuid;

    protected $fillable = [
        'customer_id', 'quick_trip_configuration_id', 'trip_date', 'pickup_count', 'drop_count',
        'per_trip_charge', 'total_amount',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'pickup_count' => 'integer',
        'drop_count' => 'integer',
        'per_trip_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function configuration()
    {
        return $this->belongsTo(QuickTripConfiguration::class, 'quick_trip_configuration_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_quick_trip_links')
            ->withTimestamps();
    }

    public function invoiceLinks()
    {
        return $this->hasMany(InvoiceQuickTripLink::class);
    }

    public function amount(): float
    {
        if ($this->total_amount !== null) {
            return (float) $this->total_amount;
        }

        $rate = (float) ($this->per_trip_charge ?? $this->configuration?->per_trip_charge ?? 0);

        return round(($this->pickup_count + $this->drop_count) * $rate, 2);
    }
}
