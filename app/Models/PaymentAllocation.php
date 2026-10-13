<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    use HasUuid;

    protected $fillable = ['payment_id', 'invoice_id', 'amount', 'notes', 'created_by'];

    protected $casts = ['amount' => 'decimal:2'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
