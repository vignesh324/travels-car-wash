<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class VehicleExpense extends Model
{
    use HasAuditFields, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'expense_date', 'vehicle_id', 'category_id', 'description',
        'vendor_name', 'reference_number',
        'amount_before_tax', 'gst_amount', 'total_amount',
        'payment_mode', 'payment_status', 'payment_date',
        'receipt_path', 'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'payment_date' => 'date',
        'amount_before_tax' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }
}
