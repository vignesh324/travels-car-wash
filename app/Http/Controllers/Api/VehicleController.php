<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('datatable')) {
            $query = Vehicle::withTrashed(false)
                ->select('vehicles.*')
                ->when($request->type, fn($q, $v) => $q->where('type', $v))
                ->when($request->fuel_type, fn($q, $v) => $q->where('fuel_type', $v))
                ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

            return DataTables::of($query)
                ->addColumn('actions', fn($row) => $row->uuid)
                ->make(true);
        }

        return response()->json(
            Vehicle::where('is_active', true)->orderBy('name')->get([
                'id', 'uuid', 'name', 'registration_number', 'type', 'default_driver_name',
            ])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'registration_number' => 'required|string|max:20|unique:vehicles,registration_number',
            'name' => 'required|string|max:255',
            'model' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:50',
            'manufacturer' => 'nullable|string|max:100',
            'fuel_type' => 'required|in:Petrol,Diesel,CNG,Electric,Other',
            'seating_capacity' => 'nullable|integer|min:1|max:100',
            'ownership_type' => 'required|in:Owned,Leased,Attached',
            'purchase_date' => 'nullable|date',
            'default_driver_name' => 'nullable|string|max:100',
            'default_driver_phone' => 'nullable|string|max:15',
            'insurance_expiry' => 'nullable|date',
            'permit_expiry' => 'nullable|date',
            'fitness_expiry' => 'nullable|date',
            'puc_expiry' => 'nullable|date',
            'current_odometer' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $vehicle = Vehicle::create($data);

        return response()->json($vehicle, 201);
    }

    public function show(Vehicle $vehicle)
    {
        return response()->json($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'registration_number' => 'required|string|max:20|unique:vehicles,registration_number,' . $vehicle->id,
            'name' => 'required|string|max:255',
            'model' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:50',
            'manufacturer' => 'nullable|string|max:100',
            'fuel_type' => 'required|in:Petrol,Diesel,CNG,Electric,Other',
            'seating_capacity' => 'nullable|integer|min:1|max:100',
            'ownership_type' => 'required|in:Owned,Leased,Attached',
            'purchase_date' => 'nullable|date',
            'default_driver_name' => 'nullable|string|max:100',
            'default_driver_phone' => 'nullable|string|max:15',
            'insurance_expiry' => 'nullable|date',
            'permit_expiry' => 'nullable|date',
            'fitness_expiry' => 'nullable|date',
            'puc_expiry' => 'nullable|date',
            'current_odometer' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
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
