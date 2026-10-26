<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

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

    public static function dropdownOptions(?int $includeId = null): Collection
    {
        return static::query()
            ->where(function ($query) use ($includeId) {
                $query->where('is_active', true);
                if ($includeId) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
