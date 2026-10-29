<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuickTripConfiguration extends Model
{
    use HasAuditFields, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'customer_id', 'name', 'pickup_location', 'drop_location', 'route',
        'per_trip_charge', 'is_active', 'remarks',
    ];

    protected $casts = [
        'per_trip_charge' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function executions()
    {
        return $this->hasMany(QuickTripExecution::class);
    }

    public function slabs()
    {
        return $this->hasMany(QuickTripConfigurationSlab::class)
            ->orderByRaw('max_trips IS NULL')
            ->orderBy('max_trips');
    }

    public function pricingForCount(int $tripCount): array
    {
        $finiteSlabs = $this->slabs
            ->filter(fn (QuickTripConfigurationSlab $slab) => $slab->max_trips !== null)
            ->sortBy('max_trips')
            ->values();
        $baseRateCents = (int) round((float) $this->per_trip_charge * 100);
        $totalCents = 0;
        $previousThreshold = 0;
        $effectiveRateCents = $baseRateCents;

        foreach ($finiteSlabs as $slab) {
            $threshold = (int) $slab->max_trips;
            $segmentCount = max(0, min($tripCount, $threshold) - $previousThreshold);
            $totalCents += $segmentCount * $effectiveRateCents;

            if ($tripCount > $threshold) {
                $effectiveRateCents = (int) round((float) $slab->per_trip_charge * 100);
            }
            $previousThreshold = $threshold;
        }

        if ($tripCount > $previousThreshold) {
            $totalCents += ($tripCount - $previousThreshold) * $effectiveRateCents;
        }

        return [
            'per_trip_charge' => number_format($effectiveRateCents / 100, 2, '.', ''),
            'total_amount' => number_format($totalCents / 100, 2, '.', ''),
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
