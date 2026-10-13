<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id', 'business_name', 'logo', 'address', 'phone', 'email',
        'gstin', 'state', 'state_code', 'currency', 'timezone', 'date_format',
        'bank_details', 'upi_id', 'invoice_prefix', 'receipt_prefix',
        'financial_year_start', 'invoice_number_reset', 'invoice_number_next',
        'default_payment_terms', 'invoice_footer', 'tax_config',
    ];

    protected $casts = [
        'bank_details' => 'array',
        'tax_config' => 'array',
        'invoice_number_reset' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
