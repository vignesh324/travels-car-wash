<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Business Owner',
                'password' => Hash::make('password'),
            ]
        );

        $this->call(ExpenseCategorySeeder::class);
        $this->call(CarWashServiceSeeder::class);

        BusinessProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => 'Travels & Car Wash',
                'invoice_prefix' => 'INV',
                'receipt_prefix' => 'RCP',
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'date_format' => 'DD-MM-YYYY',
                'financial_year_start' => '04-01',
                'invoice_number_next' => 1,
            ]
        );
    }
}
