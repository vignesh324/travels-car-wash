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

        BusinessProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => 'SKT Travels & Car Wash',
                'address' => 'No. 12, Demo Market Road, Gandhipuram, Coimbatore, Tamil Nadu 641012',
                'phone' => '+91 90000 00000',
                'email' => 'admin@example.com',
                'state' => 'Tamil Nadu',
                'state_code' => '33',
                'upi_id' => 'skttravels@example',
                'default_payment_terms' => 'Payment due within 30 days',
                'invoice_prefix' => 'INV',
                'receipt_prefix' => 'RCP',
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'date_format' => 'DD-MM-YYYY',
                'financial_year_start' => '04-01',
                'invoice_number_next' => 1,
            ]
        );

        $this->call(DemoDataSeeder::class);
    }
}
