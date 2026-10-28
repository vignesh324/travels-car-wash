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
        'per_trip_charge',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'pickup_count' => 'integer',
        'drop_count' => 'integer',
        'per_trip_charge' => 'decimal:2',
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
}
