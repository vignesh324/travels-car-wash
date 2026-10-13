<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CarWashServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Basic Wash', 'description' => 'Exterior rinse and basic clean', 'default_price' => 150.00, 'tax_rate' => 0, 'sort_order' => 1],
            ['name' => 'Foam Wash', 'description' => 'Foam shampoo exterior wash', 'default_price' => 250.00, 'tax_rate' => 0, 'sort_order' => 2],
            ['name' => 'Interior Cleaning', 'description' => 'Interior vacuum and wipe down', 'default_price' => 300.00, 'tax_rate' => 0, 'sort_order' => 3],
            ['name' => 'Exterior Cleaning', 'description' => 'Full exterior polish and clean', 'default_price' => 350.00, 'tax_rate' => 0, 'sort_order' => 4],
            ['name' => 'Full Car Detailing', 'description' => 'Complete interior and exterior detailing', 'default_price' => 1500.00, 'tax_rate' => 18, 'sort_order' => 5],
            ['name' => 'Interior Vacuuming', 'description' => 'Deep vacuum of seats and floor', 'default_price' => 200.00, 'tax_rate' => 0, 'sort_order' => 6],
            ['name' => 'Engine Bay Cleaning', 'description' => 'Engine compartment cleaning', 'default_price' => 500.00, 'tax_rate' => 18, 'sort_order' => 7],
        ];

        foreach ($services as $service) {
            DB::table('car_wash_services')->insertOrIgnore(array_merge($service, [
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
