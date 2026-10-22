<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user     = User::factory()->create();
        $this->customer = Customer::create(['type' => 'corporate', 'name' => 'Contact', 'company_name' => 'Acme Corp', 'state' => 'Maharashtra']);
        $this->vehicle  = Vehicle::create(['registration_number' => 'MH12AB1234', 'name' => 'V1', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);
        BusinessProfile::create(['user_id' => $this->user->id, 'business_name' => 'Test Biz', 'state' => 'Maharashtra']);
    }

    private function invoicePayload(array $o = []): array
    {
        return array_merge([
            'customer_id'     => $this->customer->id,
            'invoice_date'    => '2025-06-01',
            'source'          => 'travels',
            'place_of_supply' => 'Maharashtra',
            'items'           => [[
                'description' => 'Trip Service',
                'source'      => 'travels',
                'quantity'    => 1,
                'unit_price'  => 1000,
                'tax_rate'    => 18,
            ]],
        ], $o);
    }

    // ── Invoice Store ─────────────────────────────────────────────────────────

    public function test_create_invoice_returns_201(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/invoices', $this->invoicePayload())
            ->assertCreated()
            ->assertJsonFragment(['customer_name' => 'Acme Corp']);
    }

    public function test_invoice_number_auto_generated(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/invoices', $this->invoicePayload());
        $this->assertNotNull($res->json('invoice_number'));
    }

    public function test_same_state_uses_cgst_sgst(): void
    {
        // Maharashtra → Maharashtra = intra-state
        $res = $this->actingAs($this->user)
            ->postJson('/api/invoices', $this->invoicePayload(['place_of_supply' => 'Maharashtra']));
        $res->assertCreated()->assertJsonFragment([
            'taxable_amount' => '1000.00',
            'cgst_amount'    => '90.00',
            'sgst_amount'    => '90.00',
            'igst_amount'    => '0.00',
            'total_amount'   => '1180.00',
        ]);
    }

    public function test_different_state_uses_igst(): void
    {
        $res = $this->actingAs($this->user)
            ->postJson('/api/invoices', $this->invoicePayload(['place_of_supply' => 'Karnataka']));
        $res->assertCreated()->assertJsonFragment([
            'cgst_amount'  => '0.00',
            'sgst_amount'  => '0.00',
            'igst_amount'  => '180.00',
            'total_amount' => '1180.00',
        ]);
    }

    public function test_invoice_requires_at_least_one_item(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/invoices', $this->invoicePayload(['items' => []]))
            ->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_invoice_requires_customer(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/invoices', $this->invoicePayload(['customer_id' => 9999]))
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id']);
    }

    public function test_invoice_links_trip_and_marks_invoiced(): void
    {
        $trip = $this->makeTrip();
        $res  = $this->actingAs($this->user)
            ->postJson('/api/invoices', $this->invoicePayload(['trip_ids' => [$trip->id]]));
        $res->assertCreated();
        $this->assertDatabaseHas('invoice_trip_links', ['trip_id' => $trip->id]);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'is_invoiced' => true]);
    }

    public function test_cannot_invoice_already_invoiced_trip(): void
    {
        $trip = $this->makeTrip(['is_invoiced' => true]);
        $this->actingAs($this->user)
            ->postJson('/api/invoices', $this->invoicePayload(['trip_ids' => [$trip->id]]))
            ->assertStatus(422);
    }

    // ── Invoice Show / Update ─────────────────────────────────────────────────

    public function test_show_invoice(): void
    {
        $inv = $this->makeInvoice();
        $this->actingAs($this->user)
            ->getJson("/api/invoices/{$inv->uuid}")
            ->assertOk()->assertJsonFragment(['id' => $inv->id]);
    }

    public function test_update_invoice(): void
    {
        $inv = $this->makeInvoice();
        $this->actingAs($this->user)
            ->putJson("/api/invoices/{$inv->uuid}", $this->invoicePayload([
                'items' => [['description' => 'Updated', 'source' => 'travels', 'quantity' => 2, 'unit_price' => 500, 'tax_rate' => 0]],
            ]))
            ->assertOk()->assertJsonFragment(['total_amount' => '1000.00']);
    }

    public function test_cannot_update_paid_invoice(): void
    {
        $inv = $this->makeInvoice();
        // Pay it fully
        $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 1180,
            'payment_method' => 'Cash',
            'allocations'    => [['invoice_id' => $inv->id, 'amount' => 1180]],
        ]);
        $this->actingAs($this->user)
            ->putJson("/api/invoices/{$inv->uuid}", $this->invoicePayload())
            ->assertStatus(422);
    }

    // ── Invoice Cancel ────────────────────────────────────────────────────────

    public function test_cancel_invoice(): void
    {
        $inv = $this->makeInvoice();
        $this->actingAs($this->user)
            ->postJson("/api/invoices/{$inv->uuid}/cancel", ['reason' => 'Customer request'])
            ->assertOk();
        $this->assertDatabaseHas('invoices', ['id' => $inv->id, 'status' => 'cancelled']);
    }

    public function test_cancel_unlinks_trips(): void
    {
        $trip = $this->makeTrip();
        $inv  = $this->makeInvoice(['trip_ids' => [$trip->id]]);
        $this->actingAs($this->user)
            ->postJson("/api/invoices/{$inv->uuid}/cancel", ['reason' => 'Test']);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'is_invoiced' => false]);
    }

    public function test_cannot_cancel_invoice_with_payments(): void
    {
        $inv = $this->makeInvoice();
        $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 500,
            'payment_method' => 'Cash',
            'allocations'    => [['invoice_id' => $inv->id, 'amount' => 500]],
        ]);
        $this->actingAs($this->user)
            ->postJson("/api/invoices/{$inv->uuid}/cancel", ['reason' => 'Test'])
            ->assertStatus(422);
    }

    public function test_cancel_requires_reason(): void
    {
        $inv = $this->makeInvoice();
        $this->actingAs($this->user)
            ->postJson("/api/invoices/{$inv->uuid}/cancel", [])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    // ── Invoice PDF ───────────────────────────────────────────────────────────

    public function test_invoice_pdf_download(): void
    {
        $inv = $this->makeInvoice();
        $this->actingAs($this->user)
            ->get("/api/invoices/{$inv->uuid}/pdf")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    // ── Invoice Datatable ─────────────────────────────────────────────────────

    public function test_invoices_datatable(): void
    {
        $this->makeInvoice();
        $this->actingAs($this->user)
            ->getJson('/api/invoices?datatable=1')
            ->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    public function test_invoices_filter_by_status(): void
    {
        $this->makeInvoice();
        $res = $this->actingAs($this->user)->getJson('/api/invoices?datatable=1&status=issued');
        $this->assertEquals(1, $res->json('recordsFiltered'));

        $res2 = $this->actingAs($this->user)->getJson('/api/invoices?datatable=1&status=paid');
        $this->assertEquals(0, $res2->json('recordsFiltered'));
    }

    public function test_invoices_filter_by_multiple_statuses(): void
    {
        $invoiceAttributes = [
            'invoice_date' => '2025-06-01',
            'customer_id' => $this->customer->id,
            'source' => 'travels',
            'customer_name' => 'Acme Corp',
            'total_amount' => 1180,
            'balance_due' => 680,
            'issued_at' => now(),
        ];
        $issuedInvoice = Invoice::create($invoiceAttributes + [
            'invoice_number' => 'INV-TEST-001',
            'status' => 'issued',
        ]);
        $partialInvoice = Invoice::create($invoiceAttributes + [
            'invoice_number' => 'INV-TEST-002',
            'status' => 'partial',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('invoices.getData', [
            'customer_id' => $this->customer->id,
            'status' => 'issued,partial',
            'start' => 0,
            'length' => 100,
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            [$issuedInvoice->id, $partialInvoice->id],
            array_column($response->json('data'), 'id')
        );
    }

    // ── Payments ──────────────────────────────────────────────────────────────

    public function test_record_payment(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 1000,
            'payment_method' => 'UPI',
        ]);
        $res->assertCreated()->assertJsonFragment([
            'amount'             => '1000.00',
            'unallocated_amount' => '1000.00',
            'status'             => 'active',
        ]);
        $this->assertNotNull($res->json('payment_number'));
    }

    public function test_payment_with_full_allocation_marks_invoice_paid(): void
    {
        $inv = $this->makeInvoice(); // total = 1180
        $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 1180,
            'payment_method' => 'Cash',
            'allocations'    => [['invoice_id' => $inv->id, 'amount' => 1180]],
        ])->assertCreated()->assertJsonFragment(['allocated_amount' => '1180.00', 'unallocated_amount' => '0.00']);

        $this->assertDatabaseHas('invoices', ['id' => $inv->id, 'status' => 'paid']);
    }

    public function test_partial_allocation_marks_invoice_partial(): void
    {
        $inv = $this->makeInvoice();
        $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 500,
            'payment_method' => 'Cash',
            'allocations'    => [['invoice_id' => $inv->id, 'amount' => 500]],
        ])->assertCreated();
        $this->assertDatabaseHas('invoices', ['id' => $inv->id, 'status' => 'partial', 'amount_paid' => 500]);
    }

    public function test_cannot_over_allocate(): void
    {
        $inv = $this->makeInvoice();
        $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 500,
            'payment_method' => 'Cash',
            'allocations'    => [['invoice_id' => $inv->id, 'amount' => 9999]],
        ])->assertStatus(422);
    }

    public function test_cannot_allocate_to_wrong_customer_invoice(): void
    {
        $other = Customer::create(['type' => 'individual', 'name' => 'Other']);
        $inv   = $this->makeInvoice();
        $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $other->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 500,
            'payment_method' => 'Cash',
            'allocations'    => [['invoice_id' => $inv->id, 'amount' => 500]],
        ])->assertStatus(422);
    }

    public function test_allocate_endpoint(): void
    {
        $inv = $this->makeInvoice();
        $pmt = $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 1180,
            'payment_method' => 'Cash',
        ])->json();

        $this->actingAs($this->user)
            ->postJson("/api/payments/{$pmt['uuid']}/allocate", [
                'allocations' => [['invoice_id' => $inv->id, 'amount' => 1180]],
            ])->assertOk();

        $this->assertDatabaseHas('invoices', ['id' => $inv->id, 'status' => 'paid']);
    }

    public function test_reverse_payment_restores_invoice_balance(): void
    {
        $inv = $this->makeInvoice();
        $pmt = $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 1180,
            'payment_method' => 'Cash',
            'allocations'    => [['invoice_id' => $inv->id, 'amount' => 1180]],
        ])->json();

        $this->actingAs($this->user)
            ->postJson("/api/payments/{$pmt['uuid']}/reverse", ['reason' => 'Cheque bounced'])
            ->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $pmt['id'], 'status' => 'reversed']);
        $this->assertDatabaseHas('invoices', ['id' => $inv->id, 'status' => 'issued', 'amount_paid' => 0]);
    }

    public function test_cannot_reverse_already_reversed(): void
    {
        $pmt = $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 500,
            'payment_method' => 'Cash',
        ])->json();

        $this->actingAs($this->user)->postJson("/api/payments/{$pmt['uuid']}/reverse", ['reason' => 'Test']);
        $this->actingAs($this->user)
            ->postJson("/api/payments/{$pmt['uuid']}/reverse", ['reason' => 'Again'])
            ->assertStatus(422);
    }

    public function test_reverse_requires_reason(): void
    {
        $pmt = $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 500,
            'payment_method' => 'Cash',
        ])->json();

        $this->actingAs($this->user)
            ->postJson("/api/payments/{$pmt['uuid']}/reverse", [])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_payment_receipt_pdf(): void
    {
        $pmt = $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 500,
            'payment_method' => 'Cash',
        ])->json();

        $this->actingAs($this->user)
            ->get("/api/payments/{$pmt['uuid']}/receipt")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_payments_datatable(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/payments?datatable=1')
            ->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    public function test_payment_requires_valid_method(): void
    {
        $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => '2025-06-01',
            'amount'         => 500,
            'payment_method' => 'Bitcoin',
        ])->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
    }

    // ── Auth guard ────────────────────────────────────────────────────────────

    public function test_all_endpoints_require_auth(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
        $this->getJson('/api/payments')->assertUnauthorized();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeInvoice(array $extra = []): Invoice
    {
        $res = $this->actingAs($this->user)
            ->postJson('/api/invoices', array_merge($this->invoicePayload(), $extra));
        return Invoice::find($res->json('id'));
    }

    private function makeTrip(array $overrides = []): Trip
    {
        $data = Trip::calculateAmounts(array_merge([
            'trip_date'   => '2025-06-01',
            'vehicle_id'  => $this->vehicle->id,
            'customer_id' => $this->customer->id,
            'trip_type'   => 'Local',
            'fare_amount' => 500,
            'status'      => 'completed',
        ], $overrides));
        $data['trip_number'] = 'TR/' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return Trip::create($data);
    }
}
