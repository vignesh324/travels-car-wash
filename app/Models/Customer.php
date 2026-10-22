<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Customer extends Model
{
    use HasAuditFields, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'type', 'name', 'company_name', 'contact_person', 'phone', 'email',
        'address', 'billing_address', 'gstin', 'state', 'state_code',
        'payment_terms', 'credit_limit', 'notes', 'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function dropdownOptions(?int $includeId = null): Collection
    {
        return static::query()
            ->where(function ($query) use ($includeId) {
                $query->where('is_active', true);
                if ($includeId) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function displayName(): string
    {
        return $this->type === 'corporate' && $this->company_name
            ? $this->company_name
            : $this->name;
    }
}
