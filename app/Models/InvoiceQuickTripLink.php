<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceQuickTripLink extends Model
{
    protected $fillable = ['invoice_id', 'quick_trip_execution_id'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function execution()
    {
        return $this->belongsTo(QuickTripExecution::class, 'quick_trip_execution_id');
    }
}
