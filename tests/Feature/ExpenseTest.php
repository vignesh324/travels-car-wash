<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Vehicle $vehicle;
    private ExpenseCategory $vCat;
    private ExpenseCategory $bCat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user    = User::factory()->create();
        $this->vehicle = Vehicle::create(['registration_number' => 'MH12AB1234', 'name' => 'V1', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);
        $this->vCat    = ExpenseCategory::create(['name' => 'Fuel', 'type' => 'vehicle']);
        $this->bCat    = ExpenseCategory::create(['name' => 'Office', 'type' => 'business']);
    }

    // ── Vehicle Expenses ──────────────────────────────────────────────────────

    private function vPayload(array $o = []): array
    {
        return array_merge([
            'expense_date'      => '2025-06-01',
            'vehicle_id'        => $this->vehicle->id,
            'category_id'       => $this->vCat->id,
            'amount_before_tax' => 1000,
            'gst_amount'        => 180,
            'total_amount'      => 1180,
            'payment_mode'      => 'Cash',
            'payment_status'    => 'paid',
        ], $o);
    }

    public function test_create_vehicle_expense(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/expenses/vehicle', $this->vPayload())
            ->assertCreated()
            ->assertJsonFragment(['total_amount' => '1180.00']);
    }

    public function test_server_corrects_wrong_total(): void
    {
        // Client sends wrong total — server must correct it
        $res = $this->actingAs($this->user)
            ->postJson('/api/expenses/vehicle', $this->vPayload(['total_amount' => 9999]));
        $res->assertCreated()->assertJsonFragment(['total_amount' => '1180.00']);
    }

    public function test_vehicle_expense_requires_vehicle(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/expenses/vehicle', $this->vPayload(['vehicle_id' => 9999]))
            ->assertStatus(422)->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_vehicle_expense_requires_valid_payment_status(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/expenses/vehicle', $this->vPayload(['payment_status' => 'invalid']))
            ->assertStatus(422)->assertJsonValidationErrors(['payment_status']);
    }

    public function test_show_vehicle_expense(): void
    {
        $e = $this->actingAs($this->user)->postJson('/api/expenses/vehicle', $this->vPayload())->json();
        $this->actingAs($this->user)
            ->getJson("/api/expenses/vehicle/{$e['uuid']}")
            ->assertOk()->assertJsonFragment(['id' => $e['id']]);
    }

    public function test_update_vehicle_expense(): void
    {
        $e = $this->actingAs($this->user)->postJson('/api/expenses/vehicle', $this->vPayload())->json();
        $this->actingAs($this->user)
            ->putJson("/api/expenses/vehicle/{$e['uuid']}", $this->vPayload(['amount_before_tax' => 800, 'gst_amount' => 0, 'total_amount' => 800]))
            ->assertOk()->assertJsonFragment(['amount_before_tax' => '800.00']);
    }

    public function test_delete_vehicle_expense(): void
    {
        $e = $this->actingAs($this->user)->postJson('/api/expenses/vehicle', $this->vPayload())->json();
        $this->actingAs($this->user)->deleteJson("/api/expenses/vehicle/{$e['uuid']}")->assertOk();
        $this->assertSoftDeleted('vehicle_expenses', ['id' => $e['id']]);
    }

    public function test_vehicle_expenses_datatable(): void
    {
        $this->actingAs($this->user)->postJson('/api/expenses/vehicle', $this->vPayload());
        $this->actingAs($this->user)
            ->getJson('/api/expenses/vehicle?datatable=1')
            ->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    public function test_vehicle_expenses_filter_by_vehicle(): void
    {
        $other = Vehicle::create(['registration_number' => 'DL01AB0001', 'name' => 'Other', 'fuel_type' => 'Petrol', 'ownership_type' => 'Owned']);
        $this->actingAs($this->user)->postJson('/api/expenses/vehicle', $this->vPayload());
        $this->actingAs($this->user)->postJson('/api/expenses/vehicle', $this->vPayload(['vehicle_id' => $other->id]));

        $res = $this->actingAs($this->user)
            ->getJson("/api/expenses/vehicle?datatable=1&vehicle_id={$this->vehicle->id}");
        $this->assertEquals(1, $res->json('recordsFiltered'));
    }

    // ── Business Expenses ─────────────────────────────────────────────────────

    private function bPayload(array $o = []): array
    {
        return array_merge([
            'expense_date'      => '2025-06-01',
            'category_id'       => $this->bCat->id,
            'source'            => 'general',
            'amount_before_tax' => 2000,
            'gst_amount'        => 360,
            'total_amount'      => 2360,
            'payment_mode'      => 'Bank Transfer',
            'payment_status'    => 'paid',
        ], $o);
    }

    public function test_create_business_expense(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/expenses/business', $this->bPayload())
            ->assertCreated()->assertJsonFragment(['total_amount' => '2360.00']);
    }

    public function test_business_expense_requires_valid_source(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/expenses/business', $this->bPayload(['source' => 'invalid']))
            ->assertStatus(422)->assertJsonValidationErrors(['source']);
    }

    public function test_update_business_expense(): void
    {
        $e = $this->actingAs($this->user)->postJson('/api/expenses/business', $this->bPayload())->json();
        $this->actingAs($this->user)
            ->putJson("/api/expenses/business/{$e['uuid']}", $this->bPayload(['source' => 'carwash', 'amount_before_tax' => 700, 'gst_amount' => 0, 'total_amount' => 700]))
            ->assertOk()->assertJsonFragment(['source' => 'carwash', 'amount_before_tax' => '700.00']);
    }

    public function test_business_expense_edit_form_loads_existing_fields_and_category(): void
    {
        $category = ExpenseCategory::create(['name' => 'Cleaning Supplies', 'type' => 'carwash']);
        $expense = $this->actingAs($this->user)
            ->postJson('/api/expenses/business', $this->bPayload([
                'category_id' => $category->id,
                'source' => 'carwash',
                'description' => 'Wash detergent',
                'vendor_name' => 'Demo Supplier',
            ]))
            ->assertCreated()
            ->json();

        $this->getJson(route('expenses.addEdit', ['type' => 'business', 'id' => $expense['id']]))
            ->assertOk()
            ->assertJsonPath('formUrl', route('expenses.business.update', $expense['id']))
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'Cleaning Supplies</option>')
                && str_contains($html, 'value="' . $category->id . '" selected')
                && str_contains($html, 'value="carwash" selected')
                && str_contains($html, 'value="Wash detergent"')
                && str_contains($html, 'value="Demo Supplier"')
                && str_contains($html, 'value="2000.00"'));
    }

    public function test_vehicle_and_business_expenses_have_separate_pages(): void
    {
        $this->actingAs($this->user);

        $this->get(route('expenses.vehicle.index'))
            ->assertOk()
            ->assertViewIs('expenses.vehicle')
            ->assertSee('Vehicle expenses')
            ->assertSee('id="expense-vehicle-filter"', false)
            ->assertSee('data-type="vehicle"', false)
            ->assertDontSee('switchExpenseTab');

        $this->get(route('expenses.business.index'))
            ->assertOk()
            ->assertViewIs('expenses.business')
            ->assertSee('Business expenses')
            ->assertSee('id="expense-source-filter"', false)
            ->assertSee('data-type="business"', false)
            ->assertDontSee('switchExpenseTab');
    }

    public function test_legacy_expenses_page_redirects_to_vehicle_expenses(): void
    {
        $this->actingAs($this->user)
            ->get(route('expenses.index', ['action' => 'new']))
            ->assertRedirect(route('expenses.vehicle.index', ['action' => 'new']));
    }

    public function test_delete_business_expense(): void
    {
        $e = $this->actingAs($this->user)->postJson('/api/expenses/business', $this->bPayload())->json();
        $this->actingAs($this->user)->deleteJson("/api/expenses/business/{$e['uuid']}")->assertOk();
        $this->assertSoftDeleted('business_expenses', ['id' => $e['id']]);
    }

    public function test_business_expenses_datatable(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/expenses/business?datatable=1')
            ->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    // ── Expense Categories ────────────────────────────────────────────────────

    public function test_list_categories(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/expense-categories')
            ->assertOk()->assertJsonStructure([['id', 'name', 'type']]);
    }

    public function test_create_category(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/expense-categories', ['name' => 'Tyres', 'type' => 'vehicle'])
            ->assertCreated()->assertJsonFragment(['name' => 'Tyres']);
    }

    public function test_update_category(): void
    {
        $c = ExpenseCategory::create(['name' => 'Old', 'type' => 'vehicle']);
        $this->actingAs($this->user)
            ->putJson("/api/expense-categories/{$c->id}", ['name' => 'New', 'type' => 'vehicle'])
            ->assertOk()->assertJsonFragment(['name' => 'New']);
    }

    // ── Auth guard ────────────────────────────────────────────────────────────

    public function test_all_endpoints_require_auth(): void
    {
        $this->getJson('/api/expenses/vehicle')->assertUnauthorized();
        $this->getJson('/api/expenses/business')->assertUnauthorized();
        $this->getJson('/api/expense-categories')->assertUnauthorized();
    }
}
