<?php

namespace Tests\Feature;

use App\Models\CarWashService;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarWashTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user     = User::factory()->create();
        $this->customer = Customer::create(['type' => 'individual', 'name' => 'Walk-in']);
    }

    // ── Services ──────────────────────────────────────────────────────────────

    public function test_create_service(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/car-wash/services', ['name' => 'Full Wash', 'default_price' => 300, 'tax_rate' => 18])
            ->assertCreated()->assertJsonFragment(['name' => 'Full Wash', 'default_price' => '300.00']);
    }

    public function test_service_requires_name_and_price(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/car-wash/services', [])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'default_price']);
    }

    public function test_list_services_returns_only_active(): void
    {
        CarWashService::create(['name' => 'Active', 'default_price' => 150, 'is_active' => true]);
        CarWashService::create(['name' => 'Inactive', 'default_price' => 100, 'is_active' => false]);

        $res = $this->actingAs($this->user)->getJson('/api/car-wash/services');
        $res->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertEquals('Active', $res->json('0.name'));
    }

    public function test_update_service(): void
    {
        $svc = CarWashService::create(['name' => 'Old', 'default_price' => 200, 'is_active' => true]);
        $this->actingAs($this->user)
            ->putJson("/api/car-wash/services/{$svc->id}", ['name' => 'New', 'default_price' => 250, 'is_active' => true])
            ->assertOk()->assertJsonFragment(['name' => 'New', 'default_price' => '250.00']);
    }

    // ── Transactions ──────────────────────────────────────────────────────────

    private function txPayload(array $o = []): array
    {
        return array_merge([
            'service_datetime'     => '2025-06-01 10:00:00',
            'vehicle_registration' => 'MH12AB1234',
            'items'                => [[
                'service_name' => 'Full Wash',
                'quantity'     => 1,
                'unit_price'   => 300,
                'tax_rate'     => 18,
            ]],
        ], $o);
    }

    public function test_create_transaction_returns_201(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/car-wash/transactions', $this->txPayload())
            ->assertCreated();
    }

    public function test_transaction_number_starts_with_cw(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload());
        $this->assertStringStartsWith('CW/', $res->json('transaction_number'));
    }

    public function test_transaction_calculates_totals(): void
    {
        // 300 * 18% = 54 tax, total = 354
        $res = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload());
        $res->assertCreated()->assertJsonFragment(['total_amount' => '354.00', 'payment_status' => 'unpaid']);
    }

    public function test_multiple_items_totals(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload([
            'items' => [
                ['service_name' => 'Full Wash', 'quantity' => 1, 'unit_price' => 300, 'tax_rate' => 18],
                ['service_name' => 'Interior',  'quantity' => 1, 'unit_price' => 200, 'tax_rate' => 18],
            ],
        ]));
        // subtotal=500, tax=90, total=590
        $res->assertCreated()->assertJsonFragment(['total_amount' => '590.00']);
    }

    public function test_transaction_discount(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload([
            'discount' => 50,
            'items'    => [['service_name' => 'Wash', 'quantity' => 1, 'unit_price' => 300, 'tax_rate' => 0]],
        ]));
        $res->assertCreated()->assertJsonFragment(['discount' => '50.00', 'total_amount' => '250.00']);
    }

    public function test_payment_status_paid(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload([
            'amount_paid' => 354,
        ]));
        $res->assertCreated()->assertJsonFragment(['payment_status' => 'paid', 'balance_due' => '0.00']);
    }

    public function test_payment_status_partial(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload([
            'amount_paid' => 100,
        ]));
        $res->assertCreated()->assertJsonFragment(['payment_status' => 'partial']);
    }

    public function test_transaction_with_customer(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload([
            'customer_id' => $this->customer->id,
        ]));
        $res->assertCreated();
        $this->assertEquals($this->customer->id, $res->json('customer_id'));
    }

    public function test_service_auto_fills_price_on_select(): void
    {
        $svc = CarWashService::create(['name' => 'Premium', 'default_price' => 500, 'tax_rate' => 18, 'is_active' => true]);
        $res = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload([
            'items' => [['service_id' => $svc->id, 'service_name' => 'Premium', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 18]],
        ]));
        $res->assertCreated()->assertJsonFragment(['total_amount' => '590.00']);
    }

    public function test_show_transaction(): void
    {
        $tx = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload())->json();
        $this->actingAs($this->user)
            ->getJson("/api/car-wash/transactions/{$tx['uuid']}")
            ->assertOk()->assertJsonFragment(['id' => $tx['id']]);
    }

    public function test_update_transaction(): void
    {
        $tx = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload())->json();
        $this->actingAs($this->user)
            ->putJson("/api/car-wash/transactions/{$tx['uuid']}", $this->txPayload([
                'items' => [['service_name' => 'Premium', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0]],
            ]))
            ->assertOk()->assertJsonFragment(['total_amount' => '500.00']);
    }

    public function test_delete_transaction(): void
    {
        $tx = $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload())->json();
        $this->actingAs($this->user)->deleteJson("/api/car-wash/transactions/{$tx['uuid']}")->assertOk();
        $this->assertSoftDeleted('car_wash_transactions', ['id' => $tx['id']]);
    }

    public function test_transactions_datatable(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/car-wash/transactions?datatable=1')
            ->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    public function test_transactions_filter_by_payment_status(): void
    {
        $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload(['amount_paid' => 354])); // paid
        $this->actingAs($this->user)->postJson('/api/car-wash/transactions', $this->txPayload(['amount_paid' => 0]));   // unpaid

        $res = $this->actingAs($this->user)->getJson('/api/car-wash/transactions?datatable=1&payment_status=paid');
        $this->assertEquals(1, $res->json('recordsFiltered'));
    }

    public function test_transaction_requires_items(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/car-wash/transactions', ['service_datetime' => '2025-06-01 10:00:00', 'items' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    // ── Auth guard ────────────────────────────────────────────────────────────

    public function test_all_endpoints_require_auth(): void
    {
        $this->getJson('/api/car-wash/services')->assertUnauthorized();
        $this->getJson('/api/car-wash/transactions')->assertUnauthorized();
    }
}
