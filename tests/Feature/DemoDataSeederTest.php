<?php

namespace Tests\Feature;

use App\Models\BusinessExpense;
use App\Models\CarWashTransaction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_all_business_modules_and_is_repeatable(): void
    {
        $this->seed();
        $this->seed();

        $user = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame(3, Customer::count());
        $this->assertSame(3, Vehicle::count());
        $this->assertSame(6, Trip::count());
        $this->assertSame(3, Invoice::count());
        $this->assertSame(2, Payment::count());
        $this->assertSame(2, VehicleExpense::count());
        $this->assertSame(2, BusinessExpense::count());
        $this->assertSame(3, CarWashTransaction::count());
        $this->assertSame('SKT Travels & Car Wash', $user->businessProfile->business_name);
        $this->assertStringContainsString('Coimbatore', $user->businessProfile->address);
        $this->assertSame('Tamil Nadu', $user->businessProfile->state);
        $this->assertSame('33', $user->businessProfile->state_code);
        $this->assertSame(3, Customer::where('state', 'Tamil Nadu')->count());
        $this->assertSame(3, Vehicle::where('registration_number', 'like', 'TN37%')->count());
        $this->assertSame(4, Trip::where('is_invoiced', true)->count());
        $this->assertSame(1, Invoice::where('status', 'partial')->count());
        $this->assertSame(1, Invoice::where('status', 'paid')->count());
        $this->assertSame(1, Invoice::where('status', 'issued')->count());
    }
}
