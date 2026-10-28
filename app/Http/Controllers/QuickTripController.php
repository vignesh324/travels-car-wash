<?php

namespace App\Http\Controllers;

use App\Http\Traits\AjaxDataTableTrait;
use App\Models\Customer;
use App\Models\QuickTripConfiguration;
use App\Models\QuickTripExecution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuickTripController extends Controller
{
    use AjaxDataTableTrait;

    public function executionIndex()
    {
        $customers = Customer::dropdownOptions();

        return view('quick-trips.executions', compact('customers'));
    }

    public function executionData(Request $request)
    {
        $query = QuickTripExecution::query()
            ->with([
                'customer:id,name,company_name,type',
                'configuration:id,name,pickup_location,drop_location,route,is_active',
            ])
            ->withExists(['invoices as is_invoiced'])
            ->when($request->customer_id, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('trip_date', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('trip_date', '<=', $date))
            ->when($request->invoice_status === 'invoiced', fn ($query) => $query->whereHas(
                'invoices'
            ))
            ->when($request->invoice_status === 'uninvoiced', fn ($query) => $query->whereDoesntHave(
                'invoices'
            ));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('customer_name', fn (QuickTripExecution $execution) => $execution->customer?->displayName() ?? '—')
                ->addColumn('configuration_name', fn (QuickTripExecution $execution) => $execution->configuration?->name ?? '—')
                ->addColumn('pickup_location', fn (QuickTripExecution $execution) => $execution->configuration?->pickup_location ?? '—')
                ->addColumn('drop_location', fn (QuickTripExecution $execution) => $execution->configuration?->drop_location ?? '—')
                ->addColumn('route', fn (QuickTripExecution $execution) => $execution->configuration?->route ?? '—')
                ->addColumn('total_count', fn (QuickTripExecution $execution) => $execution->pickup_count + $execution->drop_count)
                ->addColumn('total_amount', fn (QuickTripExecution $execution) => round(
                    ($execution->pickup_count + $execution->drop_count)
                        * (float) $execution->per_trip_charge,
                    2
                ))
                ->addColumn('invoice_status', fn (QuickTripExecution $execution) => $execution->is_invoiced
                    ? '<span class="badge text-bg-success">Invoiced</span>'
                    : '<span class="badge text-bg-warning">Uninvoiced</span>')
                ->addColumn('actions', function (QuickTripExecution $execution) {
                    if ($execution->is_invoiced) {
                        return '<span class="text-muted small">Billed</span>';
                    }
                    if (! $execution->configuration?->is_active) {
                        return '<span class="text-muted small">Configuration inactive</span>';
                    }

                    return '<button type="button" class="btn btn-outline-primary btn-sm" data-action="quick-trip-executions.edit" data-customer-id="'.e($execution->customer_id).'" data-trip-date="'.e($execution->trip_date->toDateString()).'" data-configuration-id="'.e($execution->quick_trip_configuration_id).'"><i class="bi bi-pencil" aria-hidden="true"></i><span class="ms-1">Edit counts</span></button>';
                })
                ->rawColumns(['invoice_status', 'actions']);
        });
    }

    public function configurations()
    {
        $customers = Customer::dropdownOptions();
        $selectedCustomerId = request()->query('customer_id');

        return view('quick-trips.configurations', compact('customers', 'selectedCustomerId'));
    }

    public function configurationAddEdit(?QuickTripConfiguration $configuration = null)
    {
        $customers = Customer::dropdownOptions($configuration?->customer_id);
        $pageTitle = $configuration ? 'Edit Quick Trip Configuration' : 'Add Quick Trip Configuration';
        $formUrl = $configuration
            ? route('quick-trip-configurations.update', $configuration)
            : route('quick-trip-configurations.store');
        $selectedCustomerId = request()->query('customer_id', $configuration?->customer_id);
        $html = view('quick-trips.configuration-form', compact(
            'configuration',
            'customers',
            'formUrl',
            'selectedCustomerId'
        ))->render();

        return response()->json(compact('pageTitle', 'formUrl', 'html', 'configuration', 'customers'));
    }

    public function configurationData(Request $request)
    {
        $query = QuickTripConfiguration::query()
            ->with('customer:id,name,company_name,type')
            ->when($request->customer_id, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('customer_name', fn (QuickTripConfiguration $configuration) => $configuration->customer?->displayName() ?? '—')
                ->addColumn('actions', fn (QuickTripConfiguration $configuration) => '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions">'
                    .'<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="quick-trips.view" data-configuration-id="'.e($configuration->id).'"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></button>'
                    .'<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="quick-trips.edit" data-configuration-id="'.e($configuration->id).'"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>'
                    .'<button type="button" class="btn btn-outline-'.($configuration->is_active ? 'danger' : 'success').' btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="quick-trips.toggle" data-configuration-id="'.e($configuration->id).'" data-is-active="'.($configuration->is_active ? '0' : '1').'"><i class="bi bi-'.($configuration->is_active ? 'pause-circle' : 'play-circle').'" aria-hidden="true"></i><span>'.($configuration->is_active ? 'Deactivate' : 'Activate').'</span></button>'
                    .'</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function storeConfiguration(Request $request)
    {
        $data = $this->validateConfiguration($request);

        return response()->json(QuickTripConfiguration::create($data), 201);
    }

    public function updateConfiguration(Request $request, QuickTripConfiguration $configuration)
    {
        $data = $this->validateConfiguration($request);
        if ((int) $data['customer_id'] !== (int) $configuration->customer_id && $configuration->executions()->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => 'A Quick Trip configuration with execution history cannot be moved to another customer.',
            ]);
        }

        $configuration->update($data);

        return response()->json($configuration->fresh());
    }

    public function toggleConfiguration(QuickTripConfiguration $configuration)
    {
        $configuration->update(['is_active' => ! $configuration->is_active]);

        return response()->json($configuration->fresh());
    }

    public function showConfiguration(QuickTripConfiguration $configuration)
    {
        return response()->json($configuration->load('customer:id,name,company_name,type'));
    }

    public function execution()
    {
        if (! request()->boolean('modal')) {
            return redirect()->route('trips.index');
        }

        $customers = Customer::dropdownOptions();
        $initialCustomerId = request()->query('customer_id');
        $initialTripDate = request()->query('trip_date', now()->toDateString());
        $initialConfigurationId = request()->query('configuration_id');
        $html = view('quick-trips.execution-form', compact(
            'customers',
            'initialCustomerId',
            'initialTripDate',
            'initialConfigurationId'
        ))->render();

        return response()->json([
            'pageTitle' => 'Quick Trip Execution',
            'formUrl' => route('trips.quick-execution.generate'),
            'html' => $html,
        ]);
    }

    public function activeConfigurations(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'trip_date' => 'required|date',
        ]);

        $configurations = QuickTripConfiguration::query()
            ->where('customer_id', $validated['customer_id'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'pickup_location', 'drop_location', 'route', 'per_trip_charge']);

        $executions = QuickTripExecution::query()
            ->whereIn('quick_trip_configuration_id', $configurations->pluck('id'))
            ->whereDate('trip_date', $validated['trip_date'])
            ->get()
            ->keyBy('quick_trip_configuration_id');

        return response()->json($configurations->map(function (QuickTripConfiguration $configuration) use ($executions) {
            $execution = $executions->get($configuration->id);

            return array_merge($configuration->toArray(), [
                'pickup_count' => $execution?->pickup_count ?? 0,
                'drop_count' => $execution?->drop_count ?? 0,
                'per_trip_charge' => $execution?->per_trip_charge ?? $configuration->per_trip_charge,
            ]);
        })->values());
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'trip_date' => 'required|date',
            'entries' => 'required|array|min:1',
            'entries.*.configuration_id' => 'required|integer|distinct|exists:quick_trip_configurations,id',
            'entries.*.pickup_count' => 'required|integer|min:0',
            'entries.*.drop_count' => 'required|integer|min:0',
        ]);

        $result = DB::transaction(function () use ($validated) {
            $customer = Customer::query()
                ->whereKey($validated['customer_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $customer->is_active) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Select an active customer.',
                ]);
            }

            $configurationIds = collect($validated['entries'])
                ->pluck('configuration_id')
                ->unique()
                ->values();
            $configurations = QuickTripConfiguration::query()
                ->where('customer_id', $customer->id)
                ->where('is_active', true)
                ->whereIn('id', $configurationIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($configurations->count() !== $configurationIds->count()) {
                throw ValidationException::withMessages([
                    'entries' => 'Every selected Quick Trip must be active and belong to the selected customer.',
                ]);
            }

            $created = 0;
            $updated = 0;
            foreach ($validated['entries'] as $entry) {
                $configuration = $configurations->get($entry['configuration_id']);
                $execution = QuickTripExecution::query()
                    ->where('quick_trip_configuration_id', $configuration->id)
                    ->whereDate('trip_date', $validated['trip_date'])
                    ->lockForUpdate()
                    ->first();

                if ($execution && $execution->invoices()->exists()) {
                    if ((int) $execution->pickup_count !== (int) $entry['pickup_count']
                        || (int) $execution->drop_count !== (int) $entry['drop_count']) {
                        throw ValidationException::withMessages([
                            'entries' => "Cannot change {$configuration->name}; it has previously been linked to an invoice.",
                        ]);
                    }

                    continue;
                }

                if (! $execution) {
                    $execution = QuickTripExecution::create([
                        'customer_id' => $customer->id,
                        'quick_trip_configuration_id' => $configuration->id,
                        'trip_date' => $validated['trip_date'],
                        'pickup_count' => (int) $entry['pickup_count'],
                        'drop_count' => (int) $entry['drop_count'],
                        'per_trip_charge' => $configuration->per_trip_charge,
                    ]);
                    $created++;
                } else {
                    $changes = [
                        'pickup_count' => (int) $entry['pickup_count'],
                        'drop_count' => (int) $entry['drop_count'],
                    ];
                    if ($execution->per_trip_charge === null) {
                        $changes['per_trip_charge'] = $configuration->per_trip_charge;
                    }
                    if ($execution->pickup_count !== $changes['pickup_count']
                        || $execution->drop_count !== $changes['drop_count']
                        || $execution->per_trip_charge === null) {
                        $execution->update($changes);
                        $updated++;
                    }
                }
            }

            activity()->causedBy(Auth::user())
                ->performedOn($customer)
                ->withProperties([
                    'trip_date' => $validated['trip_date'],
                    'created' => $created,
                    'updated' => $updated,
                ])
                ->log('Quick Trip executions saved');

            return compact('created', 'updated');
        });

        return response()->json([
            'message' => 'Quick Trip execution saved successfully. No individual trips were created.',
            'created' => $result['created'],
            'updated' => $result['updated'],
        ]);
    }

    private function validateConfiguration(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'name' => 'required|string|max:255',
            'pickup_location' => 'required|string|max:255',
            'drop_location' => 'required|string|max:255',
            'route' => 'nullable|string|max:255',
            'per_trip_charge' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'remarks' => 'nullable|string',
        ]);
    }
}
