<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Payment extends Model
{
    use HasAuditFields, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'payment_number', 'customer_id', 'payment_date', 'amount',
        'allocated_amount', 'unallocated_amount',
        'payment_method', 'transaction_reference', 'notes',
        'status', 'reversal_reason', 'reversed_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'reversed_at' => 'datetime',
        'amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'unallocated_amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')
            ->withPivot('amount', 'notes')
            ->withTimestamps();
    }

    public function recalculateAllocated(): void
    {
        $allocated = $this->allocations()->sum('amount');
        $this->allocated_amount = $allocated;
        $this->unallocated_amount = max(0, $this->amount - $allocated);
        $this->saveQuietly();
    }
}
