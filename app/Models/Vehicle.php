<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Vehicle extends Model
{
    use HasAuditFields, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'registration_number', 'name', 'model', 'type', 'manufacturer',
        'fuel_type', 'seating_capacity', 'ownership_type', 'purchase_date',
        'default_driver_name', 'default_driver_phone',
        'insurance_expiry', 'permit_expiry', 'fitness_expiry', 'puc_expiry',
        'current_odometer', 'is_active', 'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'insurance_expiry' => 'date',
        'permit_expiry' => 'date',
        'fitness_expiry' => 'date',
        'puc_expiry' => 'date',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function vehicleExpenses()
    {
        return $this->hasMany(VehicleExpense::class);
    }
}
