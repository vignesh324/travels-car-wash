<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Vehicle operating expenses
            ['name' => 'Fuel', 'type' => 'vehicle', 'sort_order' => 1],
            ['name' => 'Driver Wages', 'type' => 'vehicle', 'sort_order' => 2],
            ['name' => 'Maintenance & Repairs', 'type' => 'vehicle', 'sort_order' => 3],
            ['name' => 'Engine Oil & Lubricants', 'type' => 'vehicle', 'sort_order' => 4],
            ['name' => 'Tyres & Punctures', 'type' => 'vehicle', 'sort_order' => 5],
            ['name' => 'Insurance', 'type' => 'vehicle', 'sort_order' => 6, 'is_capital' => false],
            ['name' => 'Permit & Fitness', 'type' => 'vehicle', 'sort_order' => 7],
            ['name' => 'PUC', 'type' => 'vehicle', 'sort_order' => 8],
            ['name' => 'Toll Charges', 'type' => 'vehicle', 'sort_order' => 9],
            ['name' => 'Parking', 'type' => 'vehicle', 'sort_order' => 10],
            ['name' => 'Cleaning', 'type' => 'vehicle', 'sort_order' => 11],
            ['name' => 'Vehicle EMI / Lease', 'type' => 'vehicle', 'sort_order' => 12, 'is_capital' => true],
            ['name' => 'Vehicle Miscellaneous', 'type' => 'vehicle', 'sort_order' => 13],
            // General business expenses
            ['name' => 'Staff Wages', 'type' => 'general', 'sort_order' => 1],
            ['name' => 'Rent', 'type' => 'general', 'sort_order' => 2],
            ['name' => 'Electricity', 'type' => 'general', 'sort_order' => 3],
            ['name' => 'Office Supplies', 'type' => 'general', 'sort_order' => 4],
            ['name' => 'Marketing', 'type' => 'general', 'sort_order' => 5],
            ['name' => 'General Miscellaneous', 'type' => 'general', 'sort_order' => 6],
            // Car wash expenses
            ['name' => 'Cleaning Supplies', 'type' => 'carwash', 'sort_order' => 1],
            ['name' => 'Equipment Maintenance', 'type' => 'carwash', 'sort_order' => 2],
            ['name' => 'Water & Electricity', 'type' => 'carwash', 'sort_order' => 3],
            ['name' => 'Car Wash Staff Wages', 'type' => 'carwash', 'sort_order' => 4],
            ['name' => 'Car Wash Miscellaneous', 'type' => 'carwash', 'sort_order' => 5],
        ];

        foreach ($categories as $cat) {
            ExpenseCategory::firstOrCreate(
                ['name' => $cat['name'], 'type' => $cat['type']],
                array_merge(['is_capital' => false, 'is_active' => true], $cat)
            );
        }
    }
}
