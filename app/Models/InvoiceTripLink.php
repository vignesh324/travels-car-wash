<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTripLink extends Model
{
    protected $fillable = ['invoice_id', 'trip_id'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
