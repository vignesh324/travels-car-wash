<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarWashTransactionItem extends Model
{
    protected $fillable = [
        'transaction_id', 'service_id', 'service_name',
        'quantity', 'unit_price', 'discount',
        'taxable_amount', 'tax_rate', 'tax_amount', 'total_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(CarWashTransaction::class, 'transaction_id');
    }

    public function service()
    {
        return $this->belongsTo(CarWashService::class, 'service_id');
    }
}
