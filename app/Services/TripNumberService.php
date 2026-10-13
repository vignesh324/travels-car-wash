<?php

namespace App\Services;

use App\Models\Trip;
use Illuminate\Support\Facades\DB;

class TripNumberService
{
    public function generate(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $prefix = "TR/{$year}{$month}/";

        $last = Trip::withTrashed()
            ->where('trip_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('trip_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
