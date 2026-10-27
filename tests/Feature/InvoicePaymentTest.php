<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\MonthlyTripInvoiceSeeder;
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

    public function test_trip_invoice_groups_vehicle_and_route_and_counts_trip_records_as_days(): void
    {
        $first = $this->makeTrip(['route' => 'School A', 'fare_amount' => 500]);
        $second = $this->makeTrip(['route' => 'School A', 'fare_amount' => 700]);
        $third = $this->makeTrip(['route' => 'School B', 'fare_amount' => 300]);

        $response = $this->actingAs($this->user)->postJson('/api/invoices', $this->invoicePayload([
            'trip_ids' => [$first->id, $second->id, $third->id],
            'trip_date_from' => '2025-06-01',
            'trip_date_to' => '2025-06-30',
            'items' => [],
        ]));

        $response->assertCreated()->assertJsonFragment(['total_amount' => '1500.00']);
        $this->assertDatabaseCount('invoice_items', 2);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $response->json('id'),
            'route' => 'School A',
            'service_days' => 2,
            'unit_price' => 1200,
            'total_amount' => 1200,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $response->json('id'),
            'route' => 'School B',
            'service_days' => 1,
            'unit_price' => 300,
            'total_amount' => 300,
        ]);

        $this->actingAs($this->user)
            ->get("/api/invoices/{$response->json('uuid')}/pdf")
            ->assertOk();
    }

    public function test_web_invoice_flow_loads_and_groups_customer_trips(): void
    {
        $trip = $this->makeTrip(['route' => 'School Route']);

        $this->actingAs($this->user)
            ->getJson('/invoices/eligible-trips?customer_id=' . $this->customer->id . '&date_from=2025-06-01&date_to=2025-06-30')
            ->assertOk()
            ->assertJsonPath('0.route', 'School Route');

        $response = $this->actingAs($this->user)->postJson('/invoices', $this->invoicePayload([
            'trip_ids' => [$trip->id],
            'trip_date_from' => '2025-06-01',
            'trip_date_to' => '2025-06-30',
            'items' => [],
        ]));

        $response->assertCreated()->assertJsonFragment(['total_amount' => '500.00']);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $response->json('id'),
            'route' => 'School Route',
            'service_days' => 1,
        ]);
    }

    public function test_invoice_page_links_to_standalone_invoice_details(): void
    {
        $this->actingAs($this->user)
            ->get('/invoices')
            ->assertOk()
            ->assertSee('Create Invoice')
            ->assertSeeText('Open an invoice')
            ->assertDontSee('modal-inv-view')
            ->assertDontSee('function viewInvoice');
    }

    public function test_invoice_add_edit_endpoint_returns_complete_blade_form(): void
    {
        $this->actingAs($this->user)
            ->getJson('/invoices/add-edit')
            ->assertOk()
            ->assertJsonPath('html', fn ($html) =>
                str_contains($html, 'id="invoice-form"')
                && str_contains($html, 'id="inp_inv_customer_id"')
                && str_contains($html, 'id="inv-trip-date-from"')
                && str_contains($html, 'id="inv-lines-wrap"')
                && str_contains($html, 'id="inv-save-btn"')
            );
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
            ->assertOk()
            ->assertJsonPath('id', $inv->id)
            ->assertJsonPath('uuid', $inv->uuid);
    }

    public function test_web_invoice_show_renders_uuid_detail_and_permitted_actions(): void
    {
        $profile = BusinessProfile::first();
        $profile->update([
            'business_name' => 'Test Biz',
            'gstin' => '29BUSINESSGST',
            'bank_details' => ['account_number' => '123456789'],
            'upi_id' => 'adu@upi',
            'default_payment_terms' => 'Payment due within 30 days',
        ]);
        $this->customer->update([
            'address' => '10 Market Road',
            'gstin' => '27CUSTOMERGST',
        ]);
        $trip = $this->makeTrip([
            'trip_date' => '2025-06-12',
            'trip_type' => 'Corporate',
            'driver_name' => 'Ravi Kumar',
            'pickup_location' => 'Coimbatore Airport',
            'drop_location' => 'City Centre',
        ]);
        $invoice = $this->makeInvoice([
            'trip_ids' => [$trip->id],
            'trip_date_from' => '2025-06-01',
            'trip_date_to' => '2025-06-30',
            'items' => [],
        ]);

        $this->actingAs($this->user)
            ->get("/invoices/{$invoice->uuid}")
            ->assertOk()
            ->assertViewIs('invoices.show')
            ->assertSee($invoice->invoice_number)
            ->assertSee('Test Biz')
            ->assertSee('Acme Corp')
            ->assertSee('27CUSTOMERGST')
            ->assertSee('29BUSINESSGST')
            ->assertSee('Payment due within 30 days')
            ->assertSee('123456789')
            ->assertSee('adu@upi')
            ->assertSee('Daily trip statement')
            ->assertSee('Corporate')
            ->assertSee('Ravi Kumar')
            ->assertSee('Coimbatore Airport')
            ->assertSee('City Centre')
            ->assertSee('Edit')
            ->assertSee('Cancel')
            ->assertSee('Download PDF')
            ->assertSee(e(route('invoices.index', ['action' => 'edit', 'uuid' => $invoice->uuid])), false)
            ->assertSee(e(route('invoices.index', ['action' => 'cancel', 'uuid' => $invoice->uuid, 'number' => $invoice->invoice_number])), false)
            ->assertSee(e(route('invoices.pdf', ['invoice' => $invoice->uuid])), false);
    }

    public function test_web_invoice_show_remains_auth_gated(): void
    {
        $this->get('/invoices/not-a-valid-invoice-uuid')
            ->assertRedirect(route('login'));
    }

    public function test_monthly_trip_invoice_seeder_creates_an_idempotent_linked_invoice(): void
    {
        $month = now()->subMonthNoOverflow()->startOfMonth();
        $invoiceNumber = 'DEMO-MONTHLY-' . $month->format('Ym');

        $this->seed(MonthlyTripInvoiceSeeder::class);

        $invoice = Invoice::where('invoice_number', $invoiceNumber)->firstOrFail();
        $tripCount = $invoice->trips()->count();

        $this->assertGreaterThanOrEqual(20, $tripCount);
        $this->assertLessThanOrEqual(23, $tripCount);
        $this->assertSame(2, $invoice->items()->count());
        $this->assertSame($tripCount, $invoice->tripLinks()->count());
        $this->assertEquals($invoice->total_amount, $invoice->trips()->sum('total_amount'));
        $this->assertSame(
            $tripCount,
            Trip::where('customer_id', $invoice->customer_id)->where('is_invoiced', true)->count()
        );

        $this->seed(MonthlyTripInvoiceSeeder::class);

        $this->assertSame(1, Invoice::where('invoice_number', $invoiceNumber)->count());
        $this->assertSame($tripCount, $invoice->trips()->count());
        $this->assertSame(2, $invoice->items()->count());
    }

    public function test_web_invoice_show_includes_all_persisted_lines_with_linked_trips(): void
    {
        $trip = $this->makeTrip(['fare_amount' => 500]);
        $invoice = $this->makeInvoice([
            'trip_ids' => [$trip->id],
            'trip_date_from' => '2025-06-01',
            'trip_date_to' => '2025-06-30',
            'items' => [[
                'description' => 'Additional legacy charge',
                'source' => 'travels',
                'quantity' => 1,
                'unit_price' => 400,
                'tax_rate' => 0,
            ]],
        ]);
        $invoice->items()
            ->where('description', 'Additional legacy charge')
            ->update([
                'vehicle_type' => 'Legacy executive service',
                'route' => 'Airport transfer',
            ]);

        $response = $this->actingAs($this->user)
            ->get("/invoices/{$invoice->uuid}")
            ->assertOk()
            ->assertSee('Daily trip statement')
            ->assertSee('Persisted invoice line items')
            ->assertSee('Additional legacy charge')
            ->assertSee('Legacy executive service')
            ->assertSee('Airport transfer')
            ->assertSee('₹900.00');

        preg_match_all('/<tr>(.*?)<\/tr>/s', $response->getContent(), $rows);
        $generatedTripSummary = collect($rows[1])->first(fn ($row) => str_contains($row, 'V1'));

        $this->assertNotNull($generatedTripSummary);
        $this->assertMatchesRegularExpression(
            '/<td class="text-end">\s*<span aria-label="Rate not specified">—<\/span>\s*<\/td>/',
            $generatedTripSummary
        );
        $this->assertStringContainsString('<td class="text-end fw-semibold">₹500.00</td>', $generatedTripSummary);
        $this->assertStringContainsString('<td class="text-end">₹400.00</td>', $response->getContent());
    }

    public function test_invoice_detail_includes_linked_trip_statement_data_and_issue_snapshots(): void
    {
        $trip = $this->makeTrip([
            'trip_date' => '2025-06-12',
            'trip_type' => 'Corporate',
            'driver_name' => 'Ravi Kumar',
            'pickup_location' => 'Coimbatore Airport',
            'drop_location' => 'City Centre',
        ]);
        $invoice = $this->makeInvoice([
            'trip_ids' => [$trip->id],
            'trip_date_from' => '2025-06-01',
            'trip_date_to' => '2025-06-30',
            'items' => [],
        ]);

        $this->customer->update(['company_name' => 'Renamed Customer']);
        BusinessProfile::first()->update(['business_name' => 'Renamed Business']);

        $this->actingAs($this->user)
            ->getJson("/api/invoices/{$invoice->uuid}")
            ->assertOk()
            ->assertJsonPath('customer_name', 'Acme Corp')
            ->assertJsonPath('business_name', 'Test Biz')
            ->assertJsonPath('business_profile.business_name', 'Renamed Business')
            ->assertJsonPath('trips.0.trip_type', 'Corporate')
            ->assertJsonPath('trips.0.pickup_location', 'Coimbatore Airport')
            ->assertJsonPath('trips.0.drop_location', 'City Centre')
            ->assertJsonPath('trips.0.driver_name', 'Ravi Kumar')
            ->assertJsonPath('trips.0.vehicle.registration_number', 'MH12AB1234');

        $this->actingAs($this->user)
            ->get("/api/invoices/{$invoice->uuid}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_trip_invoice_summary_uses_actual_trip_counts_and_amounts_without_pickup_drop_classifications(): void
    {
        $first = $this->makeTrip([
            'trip_type' => 'Corporate',
            'fare_amount' => 500,
            'pickup_location' => 'Airport',
            'drop_location' => 'City Centre',
        ]);
        $second = $this->makeTrip([
            'trip_type' => 'Local',
            'fare_amount' => 700,
            'pickup_location' => 'Railway Station',
            'drop_location' => 'Hotel',
        ]);
        $invoice = $this->makeInvoice([
            'trip_ids' => [$first->id, $second->id],
            'trip_date_from' => '2025-06-01',
            'trip_date_to' => '2025-06-30',
            'items' => [],
        ]);
        $profile = BusinessProfile::first();
        $profile->update(['default_payment_terms' => 'Payment due within 30 days']);

        $invoice->load(['customer', 'items', 'trips.vehicle']);
        $html = view('pdf.invoice', compact('invoice', 'profile'))->render();

        $this->assertStringContainsString('Total billable trip records', $html);
        $this->assertStringContainsString('Recorded trip types / counts', $html);
        $this->assertStringContainsString('Corporate: 1', $html);
        $this->assertStringContainsString('Local: 1', $html);
        $this->assertStringContainsString('Total linked trip amount', $html);
        $this->assertStringContainsString('₹1,200.00', $html);
        $this->assertStringNotContainsString('Average amount per trip', $html);
        $this->assertStringNotContainsString('Average amount / trip', $html);
        $this->assertStringContainsString('Trip amount (₹)', $html);
        $this->assertStringNotContainsString('Pickup trips', $html);
        $this->assertStringNotContainsString('Drop trips', $html);
        $this->assertStringNotContainsString('page-break-before', $html);
        $this->assertStringNotContainsString('billing-summary keep-together', $html);
        $this->assertStringContainsString('header-table billing-area keep-together', $html);
        $this->assertStringContainsString('Payment due within 30 days', $html);
        $this->assertMatchesRegularExpression('/<\/table>\s*<\/section>\s*<table class="footer-table">/', $html);

        $this->actingAs($this->user)
            ->getJson("/api/invoices/{$invoice->uuid}")
            ->assertOk()
            ->assertJsonPath('business_profile.default_payment_terms', 'Payment due within 30 days');
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

    public function test_trip_initial_payment_is_recorded_on_invoice_and_combines_with_later_payment(): void
    {
        $trip = $this->makeTrip([
            'fare_amount' => 500,
            'amount_received' => 200,
            'payment_mode' => 'UPI',
        ]);

        $invoiceResponse = $this->actingAs($this->user)->postJson('/api/invoices', $this->invoicePayload([
            'trip_ids' => [$trip->id],
            'items' => [],
        ]))->assertCreated();

        $invoice = Invoice::findOrFail($invoiceResponse->json('id'));
        $this->assertSame('200.00', $invoice->amount_paid);
        $this->assertSame('300.00', $invoice->balance_due);
        $this->assertSame('partial', $invoice->status);

        $initialPayment = $invoice->allocations()->with('payment')->firstOrFail()->payment;
        $this->assertSame('200.00', $initialPayment->amount);
        $this->assertSame('UPI', $initialPayment->payment_method);
        $this->assertSame($trip->trip_date->toDateString(), $initialPayment->payment_date->toDateString());

        $this->actingAs($this->user)->postJson('/api/payments', [
            'customer_id' => $this->customer->id,
            'payment_date' => '2025-06-02',
            'amount' => 100,
            'payment_method' => 'Cash',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 100]],
        ])->assertCreated();

        $invoice->refresh();
        $this->assertSame('300.00', $invoice->amount_paid);
        $this->assertSame('200.00', $invoice->balance_due);
        $this->assertSame(2, $invoice->allocations()->count());

        $this->actingAs($this->user)
            ->get(route('invoices.show', $invoice->uuid))
            ->assertOk()
            ->assertSee($initialPayment->payment_number)
            ->assertSee('200.00')
            ->assertSee('Payment history');
    }

    public function test_web_invoice_creation_allocates_trip_initial_payment(): void
    {
        $trip = $this->makeTrip([
            'fare_amount' => 500,
            'amount_received' => 150,
        ]);

        $response = $this->actingAs($this->user)->postJson('/invoices', $this->invoicePayload([
            'trip_ids' => [$trip->id],
            'items' => [],
        ]))->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'id' => $response->json('id'),
            'amount_paid' => 150,
            'balance_due' => 350,
            'status' => 'partial',
        ]);
        $this->assertDatabaseHas('payment_allocations', [
            'invoice_id' => $response->json('id'),
            'amount' => 150,
        ]);
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
