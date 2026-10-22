<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\TripNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\AjaxDataTableTrait;

class TripController extends Controller
{
    use AjaxDataTableTrait;

    public function addEdit(?string $uuid = null)
    {
        $trip = $uuid ? Trip::where('uuid', $uuid)->firstOrFail() : null;
        $pageTitle = $trip ? 'Edit Trip' : 'Add Trip';
        $formUrl = $trip ? route('trips.update', $trip->id) : route('trips.store');
        $vehicles = Vehicle::dropdownOptions($trip?->vehicle_id);
        $customers = Customer::dropdownOptions($trip?->customer_id);
        $html = view('trips.add_edit', compact('trip', 'formUrl', 'vehicles', 'customers'))->render();

        return response()->json(compact('pageTitle', 'formUrl', 'html', 'trip', 'vehicles', 'customers'));
    }

    public function getData(Request $request)
    {
        $query = Trip::with(['vehicle:id,name,registration_number', 'customer:id,name,company_name,type'])
            ->when($request->vehicle_id,     fn($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->customer_id,    fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->trip_type,      fn($q, $v) => $q->where('trip_type', $v))
            ->when($request->status,         fn($q, $v) => $q->where('status', $v))
            ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
            ->when($request->date_from,      fn($q, $v) => $q->whereDate('trip_date', '>=', $v))
            ->when($request->date_to,        fn($q, $v) => $q->whereDate('trip_date', '<=', $v));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('actions', fn (Trip $trip) =>
                    '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions">'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="trips.view" aria-label="' . e('View trip ' . $trip->trip_number) . '" title="View" data-trip-uuid="' . e($trip->uuid) . '"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></button>'
                    . ($trip->is_invoiced
                        ? '<span class="align-self-center small text-muted">Invoiced</span>'
                        : '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="trips.edit" aria-label="' . e('Edit trip ' . $trip->trip_number) . '" title="Edit" data-trip-uuid="' . e($trip->uuid) . '"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>'
                            . '<button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="trips.delete" aria-label="' . e('Delete trip ' . $trip->trip_number) . '" title="Delete" data-trip-uuid="' . e($trip->uuid) . '" data-trip-number="' . e($trip->trip_number) . '"><i class="bi bi-trash" aria-hidden="true"></i><span>Delete</span></button>')
                    . '</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function index()
    {
        $vehicles = Vehicle::dropdownOptions();

        return view('trips.index', compact('vehicles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_date'          => 'required|date',
            'vehicle_id'         => 'required|exists:vehicles,id',
            'customer_id'        => 'nullable|exists:customers,id',
            'trip_type'          => 'required|in:Local,Outstation,Airport Transfer,Corporate,Other',
            'pickup_location'    => 'nullable|string|max:255',
            'drop_location'      => 'nullable|string|max:255',
            'description'        => 'nullable|string',
            'start_odometer'     => 'nullable|integer|min:0',
            'end_odometer'       => 'nullable|integer|min:0|gte:start_odometer',
            'driver_name'        => 'nullable|string|max:100',
            'fare_amount'        => 'required|numeric|min:0',
            'additional_charges' => 'nullable|numeric|min:0',
            'discount'           => 'nullable|numeric|min:0',
            'is_taxable'         => 'boolean',
            'tax_rate'           => 'nullable|numeric|min:0|max:100',
            'payment_mode'       => 'nullable|string|max:50',
            'amount_received'    => 'nullable|numeric|min:0',
            'status'             => 'required|in:draft,completed,cancelled',
            'notes'              => 'nullable|string',
        ]);

        $data = Trip::calculateAmounts($validated);

        if (($data['start_odometer'] ?? null) !== null && ($data['end_odometer'] ?? null) !== null) {
            $data['distance_km'] = $data['end_odometer'] - $data['start_odometer'];
        }

        $trip = DB::transaction(function () use ($data) {
            $data['trip_number'] = app(TripNumberService::class)->generate();
            return Trip::create($data);
        });

        return response()->json($trip->load(['vehicle:id,name,registration_number', 'customer:id,name,company_name,type']), 201);
    }

    public function show(Trip $trip)
    {
        return response()->json($trip->load(['vehicle', 'customer']));
    }

    public function update(Request $request, Trip $trip)
    {
        if ($trip->is_invoiced) {
            return response()->json(['message' => 'Cannot edit an invoiced trip.'], 422);
        }

        $validated = $request->validate([
            'trip_date'          => 'required|date',
            'vehicle_id'         => 'required|exists:vehicles,id',
            'customer_id'        => 'nullable|exists:customers,id',
            'trip_type'          => 'required|in:Local,Outstation,Airport Transfer,Corporate,Other',
            'pickup_location'    => 'nullable|string|max:255',
            'drop_location'      => 'nullable|string|max:255',
            'description'        => 'nullable|string',
            'start_odometer'     => 'nullable|integer|min:0',
            'end_odometer'       => 'nullable|integer|min:0|gte:start_odometer',
            'driver_name'        => 'nullable|string|max:100',
            'fare_amount'        => 'required|numeric|min:0',
            'additional_charges' => 'nullable|numeric|min:0',
            'discount'           => 'nullable|numeric|min:0',
            'is_taxable'         => 'boolean',
            'tax_rate'           => 'nullable|numeric|min:0|max:100',
            'payment_mode'       => 'nullable|string|max:50',
            'amount_received'    => 'nullable|numeric|min:0',
            'status'             => 'required|in:draft,completed,cancelled',
            'notes'              => 'nullable|string',
        ]);

        $data = Trip::calculateAmounts($validated);

        if (($data['start_odometer'] ?? null) !== null && ($data['end_odometer'] ?? null) !== null) {
            $data['distance_km'] = $data['end_odometer'] - $data['start_odometer'];
        }

        $trip->update($data);

        return response()->json($trip->fresh()->load(['vehicle:id,name,registration_number', 'customer:id,name,company_name,type']));
    }

    public function destroy(Trip $trip)
    {
        if ($trip->is_invoiced) {
            return response()->json(['message' => 'Cannot delete an invoiced trip.'], 422);
        }

        $trip->setDeletedBy();
        $trip->delete();

        return response()->json(['message' => 'Trip deleted']);
    }

    public function summary(Request $request)
    {
        $date      = $request->date ?? now()->toDateString();
        $todayTrips = Trip::whereDate('trip_date', $date)->where('status', 'completed');

        return response()->json([
            'count'     => (clone $todayTrips)->count(),
            'revenue'   => (clone $todayTrips)->sum('total_amount'),
            'collected' => (clone $todayTrips)->sum('amount_received'),
        ]);
    }
}
