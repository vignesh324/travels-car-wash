<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasUuid;

    protected $fillable = [
        'invoice_id', 'description', 'source', 'quantity', 'unit_price', 'discount',
        'taxable_amount', 'tax_rate', 'cgst_rate', 'sgst_rate', 'igst_rate',
        'tax_amount', 'total_amount', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'cgst_rate' => 'decimal:2',
        'sgst_rate' => 'decimal:2',
        'igst_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
