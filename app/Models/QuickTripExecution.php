<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class QuickTripExecution extends Model
{
    use HasAuditFields, HasUuid;

    protected $appends = ['vehicle', 'vehicle_name', 'driver_name'];

    protected $fillable = [
        'customer_id', 'vehicle_id', 'quick_trip_configuration_id', 'trip_date', 'pickup_count', 'drop_count',
        'driver_name', 'per_trip_charge', 'total_amount',
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

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
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

    public function getVehicleAttribute(): ?Vehicle
    {
        if ($this->vehicle_id) {
            return $this->relationLoaded('vehicle') ? $this->getRelation('vehicle') : $this->vehicle()->first();
        }

        $trip = $this->primaryTrip();

        return $trip?->vehicle;
    }

    public function getVehicleNameAttribute(): ?string
    {
        return $this->vehicle?->name ?? $this->primaryTrip()?->vehicle?->name;
    }

    public function getDriverNameAttribute(): ?string
    {
        $driverName = $this->getAttributeFromArray('driver_name');
        if (! empty($driverName)) {
            return $driverName;
        }

        $trip = $this->primaryTrip();

        return $trip?->driver_name ?: $this->vehicle?->default_driver_name;
    }

    public function primaryTrip(): ?Trip
    {
        return $this->trips()
            ->with('vehicle')
            ->orderBy('trip_date')
            ->orderBy('quick_trip_sequence')
            ->orderBy('id')
            ->first();
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
