<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\QuickTripConfiguration;
use App\Models\QuickTripExecution;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickTripTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private QuickTripConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::create(['type' => 'corporate', 'name' => 'Contact', 'company_name' => 'Quick Trip Customer']);
        $this->configuration = $this->makeConfiguration();
    }

    private function makeConfiguration(array $overrides = []): QuickTripConfiguration
    {
        return QuickTripConfiguration::create(array_merge([
            'customer_id' => $this->customer->id,
            'name' => 'School Run',
            'pickup_location' => 'North Gate',
            'drop_location' => 'School',
            'route' => 'Route A',
            'per_trip_charge' => 125,
            'is_active' => true,
        ], $overrides));
    }

    private function generatePayload(array $entries, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'trip_date' => '2026-10-27',
            'entries' => $entries,
        ], $overrides);
    }

    private function entry(QuickTripConfiguration $configuration, int $pickup, int $drop): array
    {
        return [
            'configuration_id' => $configuration->id,
            'pickup_count' => $pickup,
            'drop_count' => $drop,
        ];
    }

    public function test_customer_can_manage_multiple_quick_trip_configurations(): void
    {
        $second = $this->makeConfiguration([
            'name' => 'Airport Run',
            'pickup_location' => 'Terminal',
            'drop_location' => 'Hotel',
        ]);

        $this->actingAs($this->user)
            ->get(route('customers.quick-trips', ['customer_id' => $this->customer->id]))
            ->assertOk()
            ->assertSee('quick-trip-configurations-dt')
            ->assertSee('Add Quick Trip')
            ->assertSee('quick-trip-filter-customer')
            ->assertSee('quickTripConfigTable = window.initDataTable');

        $this->actingAs($this->user)
            ->getJson(route('quick-trip-configurations.getData', [
                'draw' => 1,
                'start' => 0,
                'length' => 15,
                'customer_id' => $this->customer->id,
            ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('data.0.name', 'School Run');

        $this->actingAs($this->user)
            ->getJson(route('quick-trip-configurations.addEdit', ['customer_id' => $this->customer->id]))
            ->assertOk()
            ->assertJsonPath('pageTitle', 'Add Quick Trip Configuration')
            ->assertJsonPath('formUrl', route('quick-trip-configurations.store'))
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'quick-trip-config-form')
                && str_contains($html, 'onsubmit="window.saveQuickTripConfiguration(event)"')
                && str_contains($html, 'type="submit"')
                && str_contains($html, 'Trip-count price slabs')
                && str_contains($html, 'School Run') === false);

        $this->assertSame(2, $this->customer->quickTripConfigurations()->count());
        $this->assertNotSame($this->configuration->id, $second->id);
    }

    public function test_configuration_can_save_multiple_trip_count_price_slabs(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('quick-trip-configurations.store'), [
                'customer_id' => $this->customer->id,
                'name' => 'Tiered Run',
                'pickup_location' => 'North Gate',
                'drop_location' => 'School',
                'route' => null,
                'per_trip_charge' => 125,
                'quick_trip_slabs' => [
                    ['max_trips' => 5, 'per_trip_charge' => 110],
                    ['max_trips' => 10, 'per_trip_charge' => 90],
                    ['max_trips' => null, 'per_trip_charge' => 75],
                ],
                'is_active' => true,
                'remarks' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('slabs.0.max_trips', 5)
            ->assertJsonPath('slabs.1.max_trips', 10)
            ->assertJsonPath('slabs.2.max_trips', null);

        $tieredConfiguration = QuickTripConfiguration::query()->where('name', 'Tiered Run')->firstOrFail();
        $this->assertSame(3, $tieredConfiguration->slabs()->count());
        $this->actingAs($this->user)
            ->getJson(route('quick-trip-configurations.addEdit', $tieredConfiguration))
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'window.saveQuickTripConfiguration(event)')
                && str_contains($html, 'data-slabs=')
                && str_contains($html, 'max_trips')
                && str_contains($html, 'per_trip_charge')
                && str_contains($html, '90.00')
                && str_contains($html, '110.00'));

        $this->actingAs($this->user)
            ->putJson(route('quick-trip-configurations.update', $tieredConfiguration), [
                'customer_id' => $this->customer->id,
                'name' => 'Tiered Run',
                'pickup_location' => 'North Gate',
                'drop_location' => 'School',
                'per_trip_charge' => 125,
                'quick_trip_slabs' => [
                    ['max_trips' => 8, 'per_trip_charge' => 95],
                ],
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('slabs.0.max_trips', 8);
        $this->assertSame(1, $tieredConfiguration->slabs()->count());

        $this->actingAs($this->user)
            ->postJson(route('quick-trip-configurations.store'), [
                'customer_id' => $this->customer->id,
                'name' => 'Invalid Tiers',
                'pickup_location' => 'A',
                'drop_location' => 'B',
                'per_trip_charge' => 100,
                'quick_trip_slabs' => [
                    ['max_trips' => 5, 'per_trip_charge' => 90],
                    ['max_trips' => 5, 'per_trip_charge' => 80],
                ],
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quick_trip_slabs.1.max_trips');
    }

    public function test_execution_prices_only_trips_above_each_configured_threshold(): void
    {
        $this->configuration->slabs()->createMany([
            ['max_trips' => 5, 'per_trip_charge' => 110],
            ['max_trips' => 10, 'per_trip_charge' => 90],
            ['max_trips' => null, 'per_trip_charge' => 75],
        ]);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 3, 2),
            ]))
            ->assertOk();

        $execution = QuickTripExecution::firstOrFail();
        $this->assertSame('125.00', $execution->per_trip_charge);
        $this->assertSame('625.00', $execution->total_amount);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 3, 3),
            ]))
            ->assertOk()
            ->assertJsonPath('updated', 1);
        $this->assertSame('110.00', $execution->fresh()->per_trip_charge);
        $this->assertSame('735.00', $execution->fresh()->total_amount);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 5, 5),
            ]))
            ->assertOk()
            ->assertJsonPath('updated', 1);
        $this->assertSame('110.00', $execution->fresh()->per_trip_charge);
        $this->assertSame('1175.00', $execution->fresh()->total_amount);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 6, 5),
            ]))
            ->assertOk()
            ->assertJsonPath('updated', 1);
        $this->assertSame('90.00', $execution->fresh()->per_trip_charge);
        $this->assertSame('1265.00', $execution->fresh()->total_amount);

        $this->configuration->slabs()->where('max_trips', 5)->update([
            'per_trip_charge' => 50,
        ]);
        $this->configuration->slabs()->where('max_trips', 10)->update([
            'per_trip_charge' => 70,
        ]);
        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 6, 5),
            ]))
            ->assertOk()
            ->assertJsonPath('updated', 0);
        $this->assertSame('90.00', $execution->fresh()->per_trip_charge);
        $this->assertSame('1265.00', $execution->fresh()->total_amount);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 6, 6),
            ]))
            ->assertOk()
            ->assertJsonPath('updated', 1);
        $this->assertSame('70.00', $execution->fresh()->per_trip_charge);
        $this->assertSame('1015.00', $execution->fresh()->total_amount);
    }

    public function test_progressive_slab_total_is_snapshotted_and_billed_without_repricing(): void
    {
        $configuration = $this->makeConfiguration(['per_trip_charge' => 300]);
        $configuration->slabs()->create([
            'max_trips' => 10,
            'per_trip_charge' => 250,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($configuration, 6, 5),
            ]))
            ->assertOk();

        $execution = QuickTripExecution::firstOrFail();
        $this->assertSame('250.00', $execution->per_trip_charge);
        $this->assertSame('3250.00', $execution->total_amount);

        $configuration->update(['per_trip_charge' => 100]);
        $configuration->slabs()->update(['per_trip_charge' => 50]);

        $this->actingAs($this->user)
            ->getJson(route('invoices.eligible-quick-trips', [
                'customer_id' => $this->customer->id,
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertJsonFragment(['total_amount' => 3250]);

        $this->actingAs($this->user)
            ->postJson(route('invoices.store'), [
                'customer_id' => $this->customer->id,
                'invoice_date' => '2026-10-27',
                'source' => 'travels',
                'quick_trip_ids' => [$execution->id],
                'trip_date_from' => '2026-10-01',
                'trip_date_to' => '2026-10-31',
                'items' => [],
            ])
            ->assertCreated()
            ->assertJsonPath('total_amount', '3250.00');

        $invoice = \App\Models\Invoice::where('invoice_number', '!=', 'QT-LOCK-001')->firstOrFail();
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Quick Trip - School Run (11 trips)',
            'quantity' => 1,
            'unit_price' => 3250,
            'total_amount' => 3250,
        ]);
    }

    public function test_base_rate_is_used_when_no_slab_covers_the_execution_count(): void
    {
        $configuration = $this->makeConfiguration([
            'name' => 'Finite Slab Run',
            'per_trip_charge' => 65,
        ]);
        $configuration->slabs()->create([
            'max_trips' => 3,
            'per_trip_charge' => 55,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($configuration, 1, 2),
            ]))
            ->assertOk();

        $this->assertDatabaseHas('quick_trip_executions', [
            'quick_trip_configuration_id' => $configuration->id,
            'per_trip_charge' => 65,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($configuration, 2, 2),
            ]))
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('quick_trip_executions', [
            'quick_trip_configuration_id' => $configuration->id,
            'per_trip_charge' => 55,
        ]);
    }

    public function test_blank_maximum_slab_never_overrides_base_or_finite_threshold_rate(): void
    {
        $configuration = $this->makeConfiguration([
            'name' => 'Blank Threshold Run',
            'per_trip_charge' => 65,
        ]);
        $configuration->slabs()->createMany([
            ['max_trips' => null, 'per_trip_charge' => 5],
            ['max_trips' => 10, 'per_trip_charge' => 50],
        ]);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($configuration, 5, 5),
            ]))
            ->assertOk();
        $this->assertDatabaseHas('quick_trip_executions', [
            'quick_trip_configuration_id' => $configuration->id,
            'per_trip_charge' => 65,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($configuration, 6, 5),
            ]))
            ->assertOk();
        $this->assertDatabaseHas('quick_trip_executions', [
            'quick_trip_configuration_id' => $configuration->id,
            'per_trip_charge' => 50,
        ]);
    }

    public function test_execution_page_and_customer_date_configuration_loader_render(): void
    {
        $this->configuration->slabs()->create([
            'max_trips' => 5,
            'per_trip_charge' => 100,
        ]);

        $this->actingAs($this->user)
            ->get(route('trips.quick-execution'))
            ->assertRedirect(route('trips.index'));

        $this->actingAs($this->user)
            ->getJson(route('trips.quick-execution', ['modal' => 1]))
            ->assertOk()
            ->assertJsonPath('pageTitle', 'Quick Trip Execution')
            ->assertJsonPath('formUrl', route('trips.quick-execution.generate'))
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'quick-trip-execution')
                && str_contains($html, 'quick-exec-customer')
                && str_contains($html, 'quick-exec-inputs')
                && str_contains($html, 'Pickup Count')
                && str_contains($html, '<form id="quick-trip-execution-form"')
                && str_contains($html, 'type="submit"')
                && str_ends_with(trim($html), '</form>')
                && ! str_contains($html, '<table')
                && ! str_contains($html, 'quick-exec-add-row'));

        $this->actingAs($this->user)
            ->getJson(route('trips.quick-execution.configurations', [
                'customer_id' => $this->customer->id,
                'trip_date' => '2026-10-27',
            ]))
            ->assertOk()
            ->assertJsonPath('0.name', 'School Run')
            ->assertJsonPath('0.slabs.0.max_trips', 5)
            ->assertJsonPath('0.pickup_count', 0)
            ->assertJsonPath('0.drop_count', 0);
    }

    public function test_api_quick_trip_execution_supports_vehicle_and_driver_details(): void
    {
        $vehicle = Vehicle::create([
            'registration_number' => 'QT-API-001',
            'name' => 'Quick Trip Van',
            'fuel_type' => 'Diesel',
            'ownership_type' => 'Owned',
            'default_driver_name' => 'Default Driver',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/vehicles')
            ->assertOk()
            ->assertJsonPath('0.default_driver_name', 'Default Driver');

        $this->actingAs($this->user)
            ->postJson('/api/trips/quick-execution/generate', $this->generatePayload([
                $this->entry($this->configuration, 2, 1),
            ], [
                'vehicle_id' => $vehicle->id,
                'driver_name' => 'Edited Driver',
            ]))
            ->assertOk()
            ->assertJsonPath('created', 1);

        $this->assertDatabaseHas('quick_trip_executions', [
            'quick_trip_configuration_id' => $this->configuration->id,
            'vehicle_id' => $vehicle->id,
            'driver_name' => 'Edited Driver',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/trips/quick-execution/configurations?customer_id='.$this->customer->id.'&trip_date=2026-10-27')
            ->assertOk()
            ->assertJsonPath('0.vehicle_id', $vehicle->id)
            ->assertJsonPath('0.driver_name', 'Edited Driver');
    }

    public function test_execution_index_lists_saved_executions_with_saved_charge_and_filters(): void
    {
        $execution = QuickTripExecution::create([
            'customer_id' => $this->customer->id,
            'quick_trip_configuration_id' => $this->configuration->id,
            'trip_date' => '2026-10-27',
            'pickup_count' => 5,
            'drop_count' => 3,
            'per_trip_charge' => 110,
        ]);

        $this->actingAs($this->user)
            ->get(route('quick-trip-executions.index'))
            ->assertOk()
            ->assertSee('Quick Trip Executions')
            ->assertSee('quick-trip-executions-dt')
            ->assertSee('New Quick Trip Execution')
            ->assertSee('initQuickTripExecutionsTable');

        $this->actingAs($this->user)
            ->getJson(route('quick-trip-executions.getData', [
                'draw' => 1,
                'start' => 0,
                'length' => 15,
                'customer_id' => $this->customer->id,
                'date_from' => '2026-10-27',
                'date_to' => '2026-10-27',
                'invoice_status' => 'uninvoiced',
            ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.customer_name', 'Quick Trip Customer')
            ->assertJsonPath('data.0.configuration_name', 'School Run')
            ->assertJsonPath('data.0.total_count', 8)
            ->assertJsonPath('data.0.per_trip_charge', '110.00')
            ->assertJsonPath('data.0.total_amount', 880)
            ->assertJsonPath('data.0.is_invoiced', false)
            ->assertJsonPath('data.0.actions', fn ($html) => str_contains($html, 'Edit counts')
                && str_contains($html, 'data-execution-id="'.$execution->id.'"')
                && str_contains($html, 'data-configuration-id="'.$this->configuration->id.'"'))
            ->assertJsonPath('data.0.pickup_count', 5)
            ->assertJsonPath('data.0.drop_count', 3);
    }

    public function test_execution_saves_counts_without_creating_individual_trips_and_prevents_duplicate_records(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 5, 3),
            ]))
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('message', 'Quick Trip execution saved successfully. No individual trips were created.');

        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseHas('quick_trip_executions', [
            'customer_id' => $this->customer->id,
            'quick_trip_configuration_id' => $this->configuration->id,
            'pickup_count' => 5,
            'drop_count' => 3,
            'per_trip_charge' => 125,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 5, 3),
            ]))
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('updated', 0);

        $this->assertDatabaseCount('trips', 0);
        $this->assertSame(1, QuickTripExecution::count());

        $this->configuration->update(['per_trip_charge' => 300]);
        $this->assertSame('125.00', QuickTripExecution::firstOrFail()->per_trip_charge);
    }

    public function test_editing_execution_by_id_updates_the_existing_record(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 2, 1),
            ]))
            ->assertOk()
            ->assertJsonPath('created', 1);
        $execution = QuickTripExecution::firstOrFail();
        $otherCustomer = Customer::create(['type' => 'individual', 'name' => 'Updated customer']);
        $otherConfiguration = $this->makeConfiguration([
            'customer_id' => $otherCustomer->id,
            'name' => 'Updated configuration',
        ]);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($otherConfiguration, 4, 2),
            ], [
                'execution_id' => $execution->id,
                'customer_id' => $otherCustomer->id,
                'trip_date' => '2026-10-28',
            ]))
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('updated', 1);

        $this->assertSame(1, QuickTripExecution::count());
        $this->assertSame($otherCustomer->id, $execution->fresh()->customer_id);
        $this->assertSame($otherConfiguration->id, $execution->fresh()->quick_trip_configuration_id);
        $this->assertSame('2026-10-28', $execution->fresh()->trip_date->toDateString());
        $this->assertSame(4, $execution->fresh()->pickup_count);
        $this->assertSame(2, $execution->fresh()->drop_count);
    }

    public function test_execution_supports_multiple_configurations_and_count_reconciliation(): void
    {
        $second = $this->makeConfiguration(['name' => 'Airport Run', 'per_trip_charge' => 200]);
        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 2, 1),
                $this->entry($second, 1, 0),
            ]))
            ->assertOk()
            ->assertJsonPath('created', 2);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 1, 1),
            ]))
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $execution = QuickTripExecution::where('quick_trip_configuration_id', $this->configuration->id)->firstOrFail();
        $this->assertSame(1, $execution->pickup_count);
        $this->assertSame(1, $execution->drop_count);
        $this->assertSame(2, QuickTripExecution::count());
        $this->assertSame(0, Trip::count());
    }

    public function test_reconciliation_refuses_changes_to_invoiced_execution_and_rolls_back_other_rows(): void
    {
        $second = $this->makeConfiguration(['name' => 'Second Run']);
        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 0, 0),
                $this->entry($second, 1, 0),
            ]))
            ->assertOk();

        $execution = QuickTripExecution::where('quick_trip_configuration_id', $this->configuration->id)->firstOrFail();
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'QT-LOCK-001',
            'invoice_date' => '2026-10-27',
            'customer_id' => $this->customer->id,
            'source' => 'travels',
            'customer_name' => $this->customer->displayName(),
            'subtotal' => 125,
            'taxable_amount' => 125,
            'total_amount' => 125,
            'balance_due' => 125,
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        $invoice->quickTripLinks()->create(['quick_trip_execution_id' => $execution->id]);

        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 2, 0),
                $this->entry($second, 0, 0),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entries');

        $this->assertSame(0, Trip::count());
        $this->assertSame(0, $execution->fresh()->pickup_count);
        $this->assertSame(1, QuickTripExecution::where('quick_trip_configuration_id', $second->id)->first()->pickup_count);
    }

    public function test_configurations_are_customer_scoped_and_inactive_configurations_cannot_generate(): void
    {
        $otherCustomer = Customer::create(['type' => 'individual', 'name' => 'Other customer']);
        $otherConfig = $this->makeConfiguration(['customer_id' => $otherCustomer->id]);
        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($otherConfig, 1, 0),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entries');

        $this->configuration->update(['is_active' => false]);
        $this->actingAs($this->user)
            ->getJson(route('trips.quick-execution.configurations', [
                'customer_id' => $this->customer->id,
                'trip_date' => '2026-10-27',
            ]))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_configuration_customer_cannot_change_after_execution_history_exists(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 1, 0),
            ]))
            ->assertOk();
        $otherCustomer = Customer::create(['type' => 'individual', 'name' => 'Other customer']);

        $this->actingAs($this->user)
            ->putJson(route('quick-trip-configurations.update', $this->configuration->id), [
                'customer_id' => $otherCustomer->id,
                'name' => $this->configuration->name,
                'pickup_location' => $this->configuration->pickup_location,
                'drop_location' => $this->configuration->drop_location,
                'route' => $this->configuration->route,
                'per_trip_charge' => $this->configuration->per_trip_charge,
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');

        $this->assertSame($this->customer->id, $this->configuration->fresh()->customer_id);
    }

    public function test_quick_trip_executions_are_invoiceable_once_using_the_saved_rate(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 2, 1),
            ]))
            ->assertOk();

        $execution = QuickTripExecution::firstOrFail();
        $this->configuration->update(['per_trip_charge' => 300]);
        $url = route('invoices.eligible-quick-trips', [
            'customer_id' => $this->customer->id,
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]);
        $this->actingAs($this->user)->getJson($url)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['total_amount' => 375]);
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/invoices/eligible-quick-trips?customer_id='.$this->customer->id.'&date_from=2026-10-01&date_to=2026-10-31')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['total_amount' => 375]);

        $invoicePayload = [
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-10-27',
            'source' => 'travels',
            'quick_trip_ids' => [$execution->id],
            'trip_date_from' => '2026-10-01',
            'trip_date_to' => '2026-10-31',
            'items' => [],
        ];
        $this->actingAs($this->user)
            ->postJson(route('invoices.store'), $invoicePayload)
            ->assertCreated()
            ->assertJsonPath('total_amount', '375.00');

        $this->actingAs($this->user)
            ->postJson(route('invoices.store'), $invoicePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quick_trip_ids');

        $invoice = \App\Models\Invoice::where('invoice_number', '!=', 'QT-LOCK-001')->firstOrFail();
        $this->assertDatabaseHas('invoice_quick_trip_links', [
            'invoice_id' => $invoice->id,
            'quick_trip_execution_id' => $execution->id,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Quick Trip - School Run (3 trips)',
            'quantity' => 1,
            'unit_price' => 375,
            'total_amount' => 375,
        ]);
        $this->assertSame(0, Trip::count());
        $this->actingAs($this->user)
            ->get(route('invoices.show', $invoice->uuid))
            ->assertOk()
            ->assertSee('Quick Trip execution details')
            ->assertSee('School Run')
            ->assertSee('North Gate');
        $this->actingAs($this->user)
            ->get(route('invoices.pdf', $invoice->uuid))
            ->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('invoices.cancel', $invoice->id), ['reason' => 'Quick Trip invoice test'])
            ->assertOk();
        $this->actingAs($this->user)->getJson($url)
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_api_invoice_creation_links_quick_trip_execution(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('trips.quick-execution.generate'), $this->generatePayload([
                $this->entry($this->configuration, 1, 2),
            ]))
            ->assertOk();

        $execution = QuickTripExecution::firstOrFail();
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/invoices', [
                'customer_id' => $this->customer->id,
                'invoice_date' => '2026-10-27',
                'source' => 'travels',
                'quick_trip_ids' => [$execution->id],
                'trip_date_from' => '2026-10-01',
                'trip_date_to' => '2026-10-31',
                'items' => [],
            ])
            ->assertCreated()
            ->assertJsonPath('total_amount', '375.00')
            ->assertJsonPath('quick_trip_executions.0.id', $execution->id);

        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('invoice_quick_trip_links', 1);
    }
}
