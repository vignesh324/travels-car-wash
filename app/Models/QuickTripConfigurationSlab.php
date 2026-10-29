<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickTripConfigurationSlab extends Model
{
    protected $fillable = ['max_trips', 'per_trip_charge'];

    protected $casts = [
        'max_trips' => 'integer',
        'per_trip_charge' => 'decimal:2',
    ];

    public function configuration()
    {
        return $this->belongsTo(QuickTripConfiguration::class, 'quick_trip_configuration_id');
    }
}
