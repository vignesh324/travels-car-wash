<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

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

    public function vehicleExpenses()
    {
        return $this->hasMany(VehicleExpense::class, 'category_id');
    }

    public function businessExpenses()
    {
        return $this->hasMany(BusinessExpense::class, 'category_id');
    }
}
