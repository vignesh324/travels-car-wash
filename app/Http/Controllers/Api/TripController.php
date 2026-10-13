<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\TripNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class TripController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('datatable')) {
            $query = Trip::with(['vehicle:id,name,registration_number', 'customer:id,name,company_name,type'])
                ->when($request->vehicle_id, fn($q, $v) => $q->where('vehicle_id', $v))
                ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
                ->when($request->trip_type, fn($q, $v) => $q->where('trip_type', $v))
                ->when($request->status, fn($q, $v) => $q->where('status', $v))
                ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
                ->when($request->date_from, fn($q, $v) => $q->whereDate('trip_date', '>=', $v))
                ->when($request->date_to, fn($q, $v) => $q->whereDate('trip_date', '<=', $v));

            return DataTables::of($query)->make(true);
        }

        // Simple list for dropdowns
        return response()->json(
            Trip::where('status', 'completed')
                ->where('is_invoiced', false)
                ->whereDate('trip_date', '>=', now()->subMonths(3))
                ->with('vehicle:id,name,registration_number')
                ->orderByDesc('trip_date')
                ->get(['id', 'uuid', 'trip_number', 'trip_date', 'vehicle_id', 'customer_id', 'total_amount'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_date' => 'required|date',
            'vehicle_id' => 'required|exists:vehicles,id',
            'customer_id' => 'nullable|exists:customers,id',
            'trip_type' => 'required|in:Local,Outstation,Airport Transfer,Corporate,Other',
            'pickup_location' => 'nullable|string|max:255',
            'drop_location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_odometer' => 'nullable|integer|min:0',
            'end_odometer' => 'nullable|integer|min:0|gte:start_odometer',
            'driver_name' => 'nullable|string|max:100',
            'fare_amount' => 'required|numeric|min:0',
            'additional_charges' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'is_taxable' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'payment_mode' => 'nullable|string|max:50',
            'amount_received' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,completed,cancelled',
            'notes' => 'nullable|string',
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
            'trip_date' => 'required|date',
            'vehicle_id' => 'required|exists:vehicles,id',
            'customer_id' => 'nullable|exists:customers,id',
            'trip_type' => 'required|in:Local,Outstation,Airport Transfer,Corporate,Other',
            'pickup_location' => 'nullable|string|max:255',
            'drop_location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_odometer' => 'nullable|integer|min:0',
            'end_odometer' => 'nullable|integer|min:0|gte:start_odometer',
            'driver_name' => 'nullable|string|max:100',
            'fare_amount' => 'required|numeric|min:0',
            'additional_charges' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'is_taxable' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'payment_mode' => 'nullable|string|max:50',
            'amount_received' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,completed,cancelled',
            'notes' => 'nullable|string',
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
        $date = $request->date ?? now()->toDateString();

        $todayTrips = Trip::whereDate('trip_date', $date)->where('status', 'completed');

        return response()->json([
            'count' => (clone $todayTrips)->count(),
            'revenue' => (clone $todayTrips)->sum('total_amount'),
            'collected' => (clone $todayTrips)->sum('amount_received'),
        ]);
    }
}
