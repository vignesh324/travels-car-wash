<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Vehicle $vehicle;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user     = User::factory()->create();
        $this->vehicle  = Vehicle::create(['registration_number' => 'MH12AB1234', 'name' => 'Innova', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);
        $this->customer = Customer::create(['type' => 'individual', 'name' => 'Test Customer']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'trip_date'   => '2025-06-01',
            'vehicle_id'  => $this->vehicle->id,
            'customer_id' => $this->customer->id,
            'trip_type'   => 'Local',
            'fare_amount' => 500,
            'status'      => 'completed',
        ], $overrides);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_create_trip_returns_201(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/trips', $this->payload())
            ->assertCreated()
            ->assertJsonFragment(['trip_type' => 'Local']);
    }

    public function test_trip_number_auto_generated(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/trips', $this->payload());
        $res->assertCreated();
        $this->assertStringStartsWith('TR/', $res->json('trip_number'));
    }

    public function test_server_calculates_totals_correctly(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/trips', $this->payload([
            'fare_amount'        => 1000,
            'additional_charges' => 200,
            'discount'           => 100,
            'is_taxable'         => true,
            'tax_rate'           => 5,
            'amount_received'    => 500,
        ]));
        // taxable = 1000+200-100 = 1100, tax = 55, total = 1155, balance = 655
        $res->assertCreated()->assertJsonFragment([
            'taxable_amount' => '1100.00',
            'tax_amount'     => '55.00',
            'total_amount'   => '1155.00',
            'balance_due'    => '655.00',
            'payment_status' => 'partial',
        ]);
    }

    public function test_payment_status_paid_when_fully_received(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/trips', $this->payload([
            'fare_amount'     => 500,
            'amount_received' => 500,
        ]));
        $res->assertCreated()->assertJsonFragment(['payment_status' => 'paid', 'balance_due' => '0.00']);
    }

    public function test_payment_status_unpaid_when_nothing_received(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/trips', $this->payload([
            'fare_amount'     => 500,
            'amount_received' => 0,
        ]));
        $res->assertCreated()->assertJsonFragment(['payment_status' => 'unpaid', 'balance_due' => '500.00']);
    }

    public function test_distance_calculated_from_odometer(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/trips', $this->payload([
            'start_odometer' => 10000,
            'end_odometer'   => 10150,
        ]));
        $res->assertCreated()->assertJsonFragment(['distance_km' => 150]);
    }

    public function test_requires_vehicle(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/trips', $this->payload(['vehicle_id' => 9999]))
            ->assertStatus(422)->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_requires_valid_trip_type(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/trips', $this->payload(['trip_type' => 'Invalid']))
            ->assertStatus(422)->assertJsonValidationErrors(['trip_type']);
    }

    public function test_requires_valid_status(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/trips', $this->payload(['status' => 'flying']))
            ->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_end_odometer_must_be_gte_start(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/trips', $this->payload(['start_odometer' => 5000, 'end_odometer' => 4000]))
            ->assertStatus(422)->assertJsonValidationErrors(['end_odometer']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_trip(): void
    {
        $trip = $this->makeTrip();
        $this->actingAs($this->user)
            ->getJson("/api/trips/{$trip->uuid}")
            ->assertOk()->assertJsonFragment(['id' => $trip->id]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_can_update_trip(): void
    {
        $trip = $this->makeTrip();
        $this->actingAs($this->user)
            ->putJson("/api/trips/{$trip->uuid}", $this->payload(['fare_amount' => 800]))
            ->assertOk()->assertJsonFragment(['total_amount' => '800.00']);
    }

    public function test_cannot_update_invoiced_trip(): void
    {
        $trip = $this->makeTrip(['is_invoiced' => true]);
        $this->actingAs($this->user)
            ->putJson("/api/trips/{$trip->uuid}", $this->payload())
            ->assertStatus(422)->assertJsonFragment(['message' => 'Cannot edit an invoiced trip.']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_can_delete_trip(): void
    {
        $trip = $this->makeTrip();
        $this->actingAs($this->user)->deleteJson("/api/trips/{$trip->uuid}")->assertOk();
        $this->assertSoftDeleted('trips', ['id' => $trip->id]);
    }

    public function test_cannot_delete_invoiced_trip(): void
    {
        $trip = $this->makeTrip(['is_invoiced' => true]);
        $this->actingAs($this->user)
            ->deleteJson("/api/trips/{$trip->uuid}")
            ->assertStatus(422)->assertJsonFragment(['message' => 'Cannot delete an invoiced trip.']);
    }

    // ── Index / Datatable ─────────────────────────────────────────────────────

    public function test_datatable_returns_correct_structure(): void
    {
        $this->makeTrip();
        $this->actingAs($this->user)
            ->getJson('/api/trips?datatable=1')
            ->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    public function test_datatable_filter_by_vehicle(): void
    {
        $this->makeTrip();
        $other = Vehicle::create(['registration_number' => 'DL01AB0001', 'name' => 'Other', 'fuel_type' => 'Petrol', 'ownership_type' => 'Owned']);
        $this->makeTrip(['vehicle_id' => $other->id]);

        $res = $this->actingAs($this->user)
            ->getJson("/api/trips?datatable=1&vehicle_id={$this->vehicle->id}");
        $res->assertOk();
        $this->assertEquals(1, $res->json('recordsFiltered'));
    }

    public function test_datatable_filter_by_status(): void
    {
        $this->makeTrip(['status' => 'completed']);
        $this->makeTrip(['status' => 'draft']);

        $res = $this->actingAs($this->user)->getJson('/api/trips?datatable=1&status=draft');
        $res->assertOk();
        $this->assertEquals(1, $res->json('recordsFiltered'));
    }

    public function test_datatable_filter_by_payment_status(): void
    {
        $this->makeTrip(['fare_amount' => 500, 'amount_received' => 500]); // paid
        $this->makeTrip(['fare_amount' => 500, 'amount_received' => 0]);   // unpaid

        $res = $this->actingAs($this->user)->getJson('/api/trips?datatable=1&payment_status=paid');
        $res->assertOk();
        $this->assertEquals(1, $res->json('recordsFiltered'));
    }

    public function test_datatable_filter_by_date_range(): void
    {
        $this->makeTrip(['trip_date' => '2025-05-01']);
        $this->makeTrip(['trip_date' => '2025-06-15']);

        $res = $this->actingAs($this->user)
            ->getJson('/api/trips?datatable=1&date_from=2025-06-01&date_to=2025-06-30');
        $res->assertOk();
        $this->assertEquals(1, $res->json('recordsFiltered'));
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    public function test_summary_endpoint(): void
    {
        $this->makeTrip(['trip_date' => now()->toDateString(), 'fare_amount' => 500, 'amount_received' => 500]);

        $res = $this->actingAs($this->user)
            ->getJson('/api/trips/summary?date=' . now()->toDateString());
        $res->assertOk()->assertJsonStructure(['count', 'revenue', 'collected']);
        $this->assertEquals(1, $res->json('count'));
    }

    // ── Auth guard ────────────────────────────────────────────────────────────

    public function test_all_endpoints_require_auth(): void
    {
        $trip = $this->makeTrip();
        $this->getJson('/api/trips')->assertUnauthorized();
        $this->postJson('/api/trips', [])->assertUnauthorized();
        $this->putJson("/api/trips/{$trip->uuid}", [])->assertUnauthorized();
        $this->deleteJson("/api/trips/{$trip->uuid}")->assertUnauthorized();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

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
