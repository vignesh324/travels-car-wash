<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\CarWashTransaction;
use App\Models\CarWashTransactionItem;
use App\Models\Customer;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Vehicle $vehicle;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user     = User::factory()->create();
        $this->vehicle  = Vehicle::create(['registration_number' => 'MH12AB1234', 'name' => 'V1', 'fuel_type' => 'Diesel', 'ownership_type' => 'Owned']);
        $this->customer = Customer::create(['type' => 'individual', 'name' => 'Test']);
        BusinessProfile::create(['user_id' => $this->user->id, 'business_name' => 'Test Biz', 'state' => 'Maharashtra']);
    }

    public function test_revenue_report_structure(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/reports/revenue?date_from=2025-01-01&date_to=2025-12-31')
            ->assertOk()->assertJsonStructure([
                'summary' => ['travels_revenue', 'travels_collected', 'carwash_revenue', 'carwash_collected', 'total_revenue', 'total_collected'],
                'trips', 'carwash',
            ]);
    }

    public function test_revenue_includes_completed_trips(): void
    {
        $this->makeTrip(['trip_date' => '2025-06-01', 'fare_amount' => 1000, 'amount_received' => 800]);
        $res = $this->actingAs($this->user)
            ->getJson('/api/reports/revenue?date_from=2025-06-01&date_to=2025-06-30');
        $this->assertEquals(1000, $res->json('summary.travels_revenue'));
        $this->assertEquals(800,  $res->json('summary.travels_collected'));
    }

    public function test_revenue_excludes_draft_trips(): void
    {
        $this->makeTrip(['trip_date' => '2025-06-01', 'fare_amount' => 1000, 'status' => 'draft']);
        $res = $this->actingAs($this->user)
            ->getJson('/api/reports/revenue?date_from=2025-06-01&date_to=2025-06-30');
        $this->assertEquals(0, $res->json('summary.travels_revenue'));
    }

    public function test_revenue_includes_carwash(): void
    {
        $this->makeCwTx('2025-06-01', 500, 500);
        $res = $this->actingAs($this->user)
            ->getJson('/api/reports/revenue?date_from=2025-06-01&date_to=2025-06-30');
        $this->assertEquals(500, $res->json('summary.carwash_revenue'));
    }

    public function test_revenue_total_combines_both(): void
    {
        $this->makeTrip(['trip_date' => '2025-06-01', 'fare_amount' => 1000]);
        $this->makeCwTx('2025-06-01', 500, 0);
        $res = $this->actingAs($this->user)
            ->getJson('/api/reports/revenue?date_from=2025-06-01&date_to=2025-06-30');
        $this->assertEquals(1500, $res->json('summary.total_revenue'));
    }

    public function test_expenses_report_structure(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/reports/expenses?date_from=2025-01-01&date_to=2025-12-31')
            ->assertOk()->assertJsonStructure([
                'summary' => ['vehicle_expenses', 'business_expenses', 'total_expenses'],
                'vehicle_expenses', 'business_expenses',
            ]);
    }

    public function test_trips_report_structure(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/reports/trips?date_from=2025-01-01&date_to=2025-12-31')
            ->assertOk()->assertJsonStructure([
                'summary' => ['count', 'revenue', 'collected', 'outstanding'],
                'trips'   => ['data'],
            ]);
    }

    public function test_trips_report_counts_correctly(): void
    {
        $this->makeTrip(['trip_date' => '2025-06-01', 'fare_amount' => 500]);
        $this->makeTrip(['trip_date' => '2025-06-02', 'fare_amount' => 700]);
        $res = $this->actingAs($this->user)
            ->getJson('/api/reports/trips?date_from=2025-06-01&date_to=2025-06-30');
        $this->assertEquals(2,    $res->json('summary.count'));
        $this->assertEquals(1200, $res->json('summary.revenue'));
    }

    public function test_trips_report_filter_by_vehicle(): void
    {
        $other = Vehicle::create(['registration_number' => 'DL01AB0001', 'name' => 'Other', 'fuel_type' => 'Petrol', 'ownership_type' => 'Owned']);
        $this->makeTrip(['trip_date' => '2025-06-01', 'fare_amount' => 500]);
        $this->makeTrip(['trip_date' => '2025-06-01', 'fare_amount' => 700, 'vehicle_id' => $other->id]);
        $res = $this->actingAs($this->user)
            ->getJson("/api/reports/trips?date_from=2025-06-01&date_to=2025-06-30&vehicle_id={$this->vehicle->id}");
        $this->assertEquals(1, $res->json('summary.count'));
    }

    public function test_invoices_report_structure(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/reports/invoices?date_from=2025-01-01&date_to=2025-12-31')
            ->assertOk()->assertJsonStructure([
                'summary'  => ['count', 'invoiced', 'collected', 'outstanding'],
                'invoices' => ['data'],
            ]);
    }

    public function test_payments_report_structure(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/reports/payments?date_from=2025-01-01&date_to=2025-12-31')
            ->assertOk()->assertJsonStructure([
                'summary'  => ['total_collected'],
                'by_method', 'payments' => ['data'],
            ]);
    }

    public function test_carwash_report_structure(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/reports/car-wash?date_from=2025-01-01&date_to=2025-12-31')
            ->assertOk()->assertJsonStructure([
                'summary'      => ['count', 'revenue', 'collected', 'outstanding'],
                'transactions' => ['data'],
            ]);
    }

    public function test_carwash_report_counts_correctly(): void
    {
        $this->makeCwTx('2025-06-01', 300, 300);
        $this->makeCwTx('2025-06-02', 500, 0);
        $res = $this->actingAs($this->user)
            ->getJson('/api/reports/car-wash?date_from=2025-06-01&date_to=2025-06-30');
        $this->assertEquals(2,   $res->json('summary.count'));
        $this->assertEquals(800, $res->json('summary.revenue'));
    }

    public function test_export_trips_xlsx(): void
    {
        $this->makeTrip(['trip_date' => '2025-06-01', 'fare_amount' => 500]);
        $res = $this->actingAs($this->user)
            ->get('/api/reports/export/trips?date_from=2025-06-01&date_to=2025-06-30');
        $res->assertOk();
        $this->assertStringContainsString('spreadsheet', $res->headers->get('Content-Type'));
    }

    public function test_export_expenses_xlsx(): void
    {
        $this->actingAs($this->user)
            ->get('/api/reports/export/expenses?date_from=2025-06-01&date_to=2025-06-30')
            ->assertOk();
    }

    public function test_export_invoices_xlsx(): void
    {
        $this->actingAs($this->user)
            ->get('/api/reports/export/invoices?date_from=2025-06-01&date_to=2025-06-30')
            ->assertOk();
    }

    public function test_activity_logs_datatable_structure(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/activity-logs?datatable=1')
            ->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    public function test_activity_logs_records_actions(): void
    {
        $this->actingAs($this->user)->postJson('/api/trips', [
            'trip_date'   => '2025-06-01',
            'vehicle_id'  => $this->vehicle->id,
            'trip_type'   => 'Local',
            'fare_amount' => 500,
            'status'      => 'completed',
        ]);
        $res = $this->actingAs($this->user)->getJson('/api/activity-logs?datatable=1');
        $this->assertGreaterThan(0, $res->json('recordsTotal'));
    }

    public function test_activity_logs_date_filter(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/activity-logs?datatable=1&date_from=2025-01-01&date_to=2025-12-31')
            ->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }

    public function test_activity_logs_search_filter(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/activity-logs?datatable=1&search[value]=created')
            ->assertOk()->assertJsonStructure(['data']);
    }

    public function test_all_report_endpoints_require_auth(): void
    {
        $this->getJson('/api/reports/revenue')->assertUnauthorized();
        $this->getJson('/api/reports/expenses')->assertUnauthorized();
        $this->getJson('/api/reports/trips')->assertUnauthorized();
        $this->getJson('/api/reports/invoices')->assertUnauthorized();
        $this->getJson('/api/reports/payments')->assertUnauthorized();
        $this->getJson('/api/reports/car-wash')->assertUnauthorized();
        $this->getJson('/api/activity-logs')->assertUnauthorized();
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

    private function makeCwTx(string $date, float $total, float $paid): CarWashTransaction
    {
        $tx = CarWashTransaction::create([
            'transaction_number' => 'CW/' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'service_datetime'   => $date . ' 10:00:00',
            'subtotal'           => $total,
            'discount'           => 0,
            'taxable_amount'     => $total,
            'tax_amount'         => 0,
            'total_amount'       => $total,
            'amount_paid'        => $paid,
            'balance_due'        => max(0, $total - $paid),
            'payment_status'     => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
        ]);
        CarWashTransactionItem::create([
            'transaction_id' => $tx->id,
            'service_name'   => 'Full Wash',
            'quantity'       => 1,
            'unit_price'     => $total,
            'taxable_amount' => $total,
            'tax_amount'     => 0,
            'total_amount'   => $total,
        ]);
        return $tx;
    }
}
