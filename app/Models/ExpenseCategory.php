<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategory extends Model
{
    use HasAuditFields, HasUuid;

    protected $fillable = [
        'name', 'type', 'description', 'is_capital', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_capital' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function dropdownOptions(?string $type = null, ?int $includeId = null): Collection
    {
        return static::query()
            ->when($type, fn($query, $value) => $query->where('type', $value))
            ->where(function ($query) use ($includeId) {
                $query->where('is_active', true);
                if ($includeId) {
                    $query->orWhereKey($includeId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function vehicleExpenses()
    {
        return $this->hasMany(VehicleExpense::class, 'category_id');
    }

    public function businessExpenses()
    {
        return $this->hasMany(BusinessExpense::class, 'category_id');
    }
}
