<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CarWashService extends Model
{
    use HasAuditFields, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'default_price', 'tax_rate', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function dropdownOptions(array $includeIds = []): Collection
    {
        return static::query()
            ->where(function ($query) use ($includeIds) {
                $query->where('is_active', true);
                if ($includeIds) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->orderBy('sort_order')
            ->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
