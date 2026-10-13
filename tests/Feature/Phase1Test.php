<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_login_with_valid_credentials(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => $this->user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['user' => ['id', 'uuid', 'name', 'email'], 'token']);
    }

    public function test_login_with_invalid_credentials_returns_422(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => $this->user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_user_when_authenticated(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonStructure(['user', 'business']);
    }

    public function test_change_password(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/auth/change-password', [
                'current_password'      => 'password',
                'password'              => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])->assertOk();
    }

    public function test_change_password_fails_with_wrong_current(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/auth/change-password', [
                'current_password'      => 'wrongpassword',
                'password'              => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])->assertStatus(422);
    }

    public function test_logout(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/auth/logout')
            ->assertOk();
    }

    // ── UUID ──────────────────────────────────────────────────────────────────

    public function test_vehicle_gets_uuid_on_creation(): void
    {
        $v = Vehicle::create([
            'registration_number' => 'MH12AB1234',
            'name'                => 'Test Vehicle',
            'fuel_type'           => 'Diesel',
            'ownership_type'      => 'Owned',
        ]);
        $this->assertNotNull($v->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $v->uuid
        );
    }

    public function test_uuids_are_unique(): void
    {
        $v1 = Vehicle::create(['registration_number' => 'MH01AA0001', 'name' => 'V1', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);
        $v2 = Vehicle::create(['registration_number' => 'MH01AA0002', 'name' => 'V2', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);
        $this->assertNotEquals($v1->uuid, $v2->uuid);
    }

    // ── Vehicles ──────────────────────────────────────────────────────────────

    public function test_vehicles_list_requires_auth(): void
    {
        $this->getJson('/api/vehicles')->assertUnauthorized();
    }

    public function test_can_create_vehicle(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/vehicles', [
                'registration_number' => 'MH12AB1234',
                'name'                => 'Toyota Innova',
                'fuel_type'           => 'Diesel',
                'ownership_type'      => 'Owned',
            ])
            ->assertCreated()
            ->assertJsonFragment(['registration_number' => 'MH12AB1234']);

        $this->assertDatabaseHas('vehicles', ['registration_number' => 'MH12AB1234']);
    }

    public function test_duplicate_registration_rejected(): void
    {
        Vehicle::create(['registration_number' => 'MH12AB1234', 'name' => 'V1', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);

        $this->actingAs($this->user)
            ->postJson('/api/vehicles', [
                'registration_number' => 'MH12AB1234',
                'name'                => 'V2',
                'fuel_type'           => 'Diesel',
                'ownership_type'      => 'Owned',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['registration_number']);
    }

    public function test_can_update_vehicle(): void
    {
        $v = Vehicle::create(['registration_number' => 'MH12AB1234', 'name' => 'Old', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);

        $this->actingAs($this->user)
            ->putJson("/api/vehicles/{$v->uuid}", [
                'registration_number' => 'MH12AB1234',
                'name'                => 'New Name',
                'fuel_type'           => 'Petrol',
                'ownership_type'      => 'Owned',
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);
    }

    public function test_can_soft_delete_vehicle(): void
    {
        $v = Vehicle::create(['registration_number' => 'MH12AB1234', 'name' => 'V1', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);

        $this->actingAs($this->user)->deleteJson("/api/vehicles/{$v->uuid}")->assertOk();
        $this->assertSoftDeleted('vehicles', ['id' => $v->id]);
    }

    public function test_audit_fields_populated_on_vehicle_create(): void
    {
        $this->actingAs($this->user)->postJson('/api/vehicles', [
            'registration_number' => 'MH12AB1234',
            'name'                => 'Test',
            'fuel_type'           => 'Diesel',
            'ownership_type'      => 'Owned',
        ]);

        $this->assertDatabaseHas('vehicles', [
            'registration_number' => 'MH12AB1234',
            'created_by'          => $this->user->id,
        ]);
    }

    public function test_vehicles_datatable(): void
    {
        Vehicle::create(['registration_number' => 'MH12AB1234', 'name' => 'V1', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);

        $this->actingAs($this->user)
            ->getJson('/api/vehicles?datatable=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    // ── Customers ─────────────────────────────────────────────────────────────

    public function test_can_create_individual_customer(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/customers', [
                'type'  => 'individual',
                'name'  => 'John Doe',
                'phone' => '9876543210',
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'John Doe']);
    }

    public function test_can_create_corporate_customer(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/customers', [
                'type'         => 'corporate',
                'name'         => 'Contact Person',
                'company_name' => 'Acme Corp',
                'gstin'        => '27AAPFU0939F1ZV',
            ])
            ->assertCreated()
            ->assertJsonFragment(['company_name' => 'Acme Corp']);
    }

    public function test_invalid_customer_type_rejected(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/customers', ['type' => 'invalid', 'name' => 'Test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_can_soft_delete_customer(): void
    {
        $c = Customer::create(['type' => 'individual', 'name' => 'Test']);

        $this->actingAs($this->user)->deleteJson("/api/customers/{$c->uuid}")->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $c->id]);
    }

    public function test_customers_datatable(): void
    {
        Customer::create(['type' => 'individual', 'name' => 'Test']);

        $this->actingAs($this->user)
            ->getJson('/api/customers?datatable=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function test_dashboard_summary(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonStructure(['today', 'month', 'outstanding_balance', 'expiry_alerts']);
    }

    public function test_dashboard_charts(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/dashboard/charts')
            ->assertOk()
            ->assertJsonStructure(['daily_revenue', 'vehicle_revenue', 'expense_breakdown']);
    }

    public function test_dashboard_recent(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/dashboard/recent')
            ->assertOk()
            ->assertJsonStructure(['recent_trips', 'recent_expenses', 'expiring_documents']);
    }

    public function test_customer_statement_endpoint(): void
    {
        $c = Customer::create(['type' => 'individual', 'name' => 'Statement Test']);

        $this->actingAs($this->user)
            ->getJson("/api/customers/{$c->uuid}/statement")
            ->assertOk()
            ->assertJsonStructure([
                'customer', 'opening_balance', 'total_invoiced', 'total_paid',
                'total_allocated', 'advance_balance', 'outstanding_balance',
                'closing_balance', 'invoices', 'payments',
            ]);
    }
}
