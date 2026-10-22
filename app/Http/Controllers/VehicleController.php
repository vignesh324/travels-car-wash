<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Traits\AjaxDataTableTrait;

class VehicleController extends Controller
{
    use AjaxDataTableTrait;

    public function addEdit(?string $uuid = null)
    {
        $vehicle = $uuid ? Vehicle::where('uuid', $uuid)->firstOrFail() : null;
        $pageTitle = $vehicle ? 'Edit Vehicle' : 'Add Vehicle';
        $formUrl = $vehicle ? route('vehicles.update', $vehicle->id) : route('vehicles.store');
        $html = view('vehicles.add_edit', [
            'vehicle' => $vehicle,
            'formUrl' => $formUrl,
            'vehicleTypes' => ['Car', 'Van', 'Bus', 'Tempo', 'SUV', 'Other'],
            'fuelTypes' => ['Petrol', 'Diesel', 'CNG', 'Electric', 'Other'],
            'ownershipTypes' => ['Owned', 'Leased', 'Attached'],
        ])->render();

        return response()->json(compact('pageTitle', 'formUrl', 'html', 'vehicle'));
    }

    public function getData(Request $request)
    {
        $query = Vehicle::query()
            ->when($request->type,      fn($q, $v) => $q->where('type', $v))
            ->when($request->fuel_type, fn($q, $v) => $q->where('fuel_type', $v))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('actions', fn (Vehicle $vehicle) =>
                    '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions vehicle-row-actions">'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="vehicles.view" aria-label="' . e('View vehicle ' . $vehicle->name . ' (' . $vehicle->registration_number . ')') . '" title="View" data-vehicle-action="view" data-vehicle-url="' . e(route('vehicles.show', ['vehicle' => $vehicle->id])) . '"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="vehicles.edit" aria-label="' . e('Edit vehicle ' . $vehicle->name . ' (' . $vehicle->registration_number . ')') . '" title="Edit" data-vehicle-action="edit" data-vehicle-uuid="' . e($vehicle->uuid) . '"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>'
                    . '<button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="vehicles.delete" aria-label="' . e('Delete vehicle ' . $vehicle->name . ' (' . $vehicle->registration_number . ')') . '" title="Delete" data-vehicle-action="delete" data-vehicle-uuid="' . e($vehicle->uuid) . '" data-vehicle-label="' . e($vehicle->name . ' (' . $vehicle->registration_number . ')') . '"><i class="bi bi-trash" aria-hidden="true"></i><span>Delete</span></button>'
                    . '</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function index()
    {
        return view('vehicles.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'registration_number' => 'required|string|max:20|unique:vehicles,registration_number',
            'name'                => 'required|string|max:255',
            'model'               => 'nullable|string|max:100',
            'type'                => 'nullable|string|max:50',
            'manufacturer'        => 'nullable|string|max:100',
            'fuel_type'           => 'required|in:Petrol,Diesel,CNG,Electric,Other',
            'seating_capacity'    => 'nullable|integer|min:1|max:100',
            'ownership_type'      => 'required|in:Owned,Leased,Attached',
            'purchase_date'       => 'nullable|date',
            'default_driver_name' => 'nullable|string|max:100',
            'default_driver_phone'=> 'nullable|string|max:15',
            'insurance_expiry'    => 'nullable|date',
            'permit_expiry'       => 'nullable|date',
            'fitness_expiry'      => 'nullable|date',
            'puc_expiry'          => 'nullable|date',
            'current_odometer'    => 'nullable|integer|min:0',
            'is_active'           => 'boolean',
            'notes'               => 'nullable|string',
        ]);

        return response()->json(Vehicle::create($data), 201);
    }

    public function show(Vehicle $vehicle)
    {
        return response()->json($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'registration_number' => 'required|string|max:20|unique:vehicles,registration_number,' . $vehicle->id,
            'name'                => 'required|string|max:255',
            'model'               => 'nullable|string|max:100',
            'type'                => 'nullable|string|max:50',
            'manufacturer'        => 'nullable|string|max:100',
            'fuel_type'           => 'required|in:Petrol,Diesel,CNG,Electric,Other',
            'seating_capacity'    => 'nullable|integer|min:1|max:100',
            'ownership_type'      => 'required|in:Owned,Leased,Attached',
            'purchase_date'       => 'nullable|date',
            'default_driver_name' => 'nullable|string|max:100',
            'default_driver_phone'=> 'nullable|string|max:15',
            'insurance_expiry'    => 'nullable|date',
            'permit_expiry'       => 'nullable|date',
            'fitness_expiry'      => 'nullable|date',
            'puc_expiry'          => 'nullable|date',
            'current_odometer'    => 'nullable|integer|min:0',
            'is_active'           => 'boolean',
            'notes'               => 'nullable|string',
        ]);

        $vehicle->update($data);

        return response()->json($vehicle->fresh());
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->setDeletedBy();
        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted']);
    }

    public function restore(string $uuid)
    {
        $vehicle = Vehicle::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $vehicle->restore();
        $vehicle->update(['deleted_by' => null]);

        activity()->causedBy(Auth::user())->performedOn($vehicle)->log('Vehicle restored');

        return response()->json($vehicle);
    }
}
