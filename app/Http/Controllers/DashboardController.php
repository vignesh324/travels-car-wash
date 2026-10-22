<?php

namespace App\Http\Controllers;

use App\Models\BusinessExpense;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $date       = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();

        $todayTrips       = Trip::whereDate('trip_date', $date)->where('status', 'completed');
        $monthTrips       = Trip::whereBetween('trip_date', [$monthStart, $monthEnd])->where('status', 'completed');

        $todayExpenses = VehicleExpense::whereDate('expense_date', $date)->where('payment_status', 'paid')->sum('total_amount')
            + BusinessExpense::whereDate('expense_date', $date)->where('payment_status', 'paid')->sum('total_amount');

        $monthExpenses = VehicleExpense::whereBetween('expense_date', [$monthStart, $monthEnd])->where('payment_status', 'paid')->sum('total_amount')
            + BusinessExpense::whereBetween('expense_date', [$monthStart, $monthEnd])->where('payment_status', 'paid')->sum('total_amount');

        $operatingExpenses = VehicleExpense::whereBetween('expense_date', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->whereHas('category', fn($q) => $q->where('is_capital', false))
            ->sum('total_amount')
            + BusinessExpense::whereBetween('expense_date', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->whereHas('category', fn($q) => $q->where('is_capital', false))
            ->sum('total_amount');

        $monthCollected = (clone $monthTrips)->sum('amount_received');

        $summary = [
            'today' => [
                'trip_count'   => (clone $todayTrips)->count(),
                'trip_revenue' => round((clone $todayTrips)->sum('total_amount'), 2),
                'collected'    => round((clone $todayTrips)->sum('amount_received'), 2),
                'expenses'     => round($todayExpenses, 2),
            ],
            'month' => [
                'revenue'          => round((clone $monthTrips)->sum('total_amount'), 2),
                'collected'        => round($monthCollected, 2),
                'expenses'         => round($monthExpenses, 2),
                'estimated_profit' => round($monthCollected - $operatingExpenses, 2),
            ],
            'outstanding_balance' => round(Trip::where('status', 'completed')->whereIn('payment_status', ['unpaid', 'partial'])->sum('balance_due'), 2),
            'expiry_alerts'       => Vehicle::where('is_active', true)->where(function ($q) {
                $q->whereBetween('insurance_expiry', [now(), now()->addDays(30)])
                  ->orWhereBetween('permit_expiry',   [now(), now()->addDays(30)])
                  ->orWhereBetween('fitness_expiry',  [now(), now()->addDays(30)])
                  ->orWhereBetween('puc_expiry',      [now(), now()->addDays(30)]);
            })->count(),
        ];

        $days = 30;
        $from = now()->subDays($days - 1)->toDateString();

        $dailyRevenue = Trip::whereBetween('trip_date', [$from, $date])
            ->where('status', 'completed')
            ->groupBy('trip_date')->orderBy('trip_date')
            ->get(['trip_date', DB::raw('SUM(total_amount) as revenue'), DB::raw('SUM(amount_received) as collected')])
            ->map(fn($r) => ['date' => $r->trip_date->format('d M'), 'revenue' => (float) $r->revenue, 'collected' => (float) $r->collected]);

        $vehicleRevenue = Trip::whereBetween('trip_date', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->with('vehicle:id,name')
            ->groupBy('vehicle_id')
            ->get(['vehicle_id', DB::raw('SUM(total_amount) as revenue')])
            ->map(fn($r) => ['vehicle' => $r->vehicle?->name ?? 'Unknown', 'revenue' => (float) $r->revenue]);

        $expenseBreakdown = VehicleExpense::whereBetween('expense_date', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->with('category:id,name')
            ->groupBy('category_id')
            ->get(['category_id', DB::raw('SUM(total_amount) as total')])
            ->map(fn($r) => ['category' => $r->category?->name ?? 'Other', 'total' => (float) $r->total]);

        $recentTrips = Trip::with(['vehicle:id,name,registration_number', 'customer:id,name,company_name,type'])
            ->where('status', 'completed')->orderByDesc('trip_date')->orderByDesc('id')->limit(5)
            ->get(['id', 'uuid', 'trip_date', 'vehicle_id', 'customer_id', 'total_amount', 'payment_status']);

        $expiringDocuments = Vehicle::where('is_active', true)->where(function ($q) {
            $q->whereBetween('insurance_expiry', [now(), now()->addDays(30)])
              ->orWhereBetween('permit_expiry',   [now(), now()->addDays(30)])
              ->orWhereBetween('fitness_expiry',  [now(), now()->addDays(30)])
              ->orWhereBetween('puc_expiry',      [now(), now()->addDays(30)]);
        })->get(['id', 'uuid', 'name', 'registration_number', 'insurance_expiry', 'permit_expiry', 'fitness_expiry', 'puc_expiry']);

        return view('dashboard.index', compact(
            'summary', 'dailyRevenue', 'vehicleRevenue', 'expenseBreakdown', 'recentTrips', 'expiringDocuments'
        ));
    }

    public function summary(Request $request)
    {
        $date       = $request->date ?? now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();

        $todayTrips          = Trip::whereDate('trip_date', $date)->where('status', 'completed');
        $todayTripCount      = (clone $todayTrips)->count();
        $todayTripRevenue    = (clone $todayTrips)->sum('total_amount');
        $todayCollected      = (clone $todayTrips)->sum('amount_received');
        $todayVehicleExp     = VehicleExpense::whereDate('expense_date', $date)->where('payment_status', 'paid')->sum('total_amount');
        $todayBusinessExp    = BusinessExpense::whereDate('expense_date', $date)->where('payment_status', 'paid')->sum('total_amount');
        $todayExpenses       = $todayVehicleExp + $todayBusinessExp;

        $monthTrips          = Trip::whereBetween('trip_date', [$monthStart, $monthEnd])->where('status', 'completed');
        $monthRevenue        = (clone $monthTrips)->sum('total_amount');
        $monthCollected      = (clone $monthTrips)->sum('amount_received');
        $monthVehicleExp     = VehicleExpense::whereBetween('expense_date', [$monthStart, $monthEnd])->where('payment_status', 'paid')->sum('total_amount');
        $monthBusinessExp    = BusinessExpense::whereBetween('expense_date', [$monthStart, $monthEnd])->where('payment_status', 'paid')->sum('total_amount');
        $monthExpenses       = $monthVehicleExp + $monthBusinessExp;

        $outstanding = Trip::where('status', 'completed')->whereIn('payment_status', ['unpaid', 'partial'])->sum('balance_due');

        $expiryAlerts = Vehicle::where('is_active', true)->where(function ($q) {
            $q->whereBetween('insurance_expiry', [now(), now()->addDays(30)])
              ->orWhereBetween('permit_expiry',   [now(), now()->addDays(30)])
              ->orWhereBetween('fitness_expiry',  [now(), now()->addDays(30)])
              ->orWhereBetween('puc_expiry',      [now(), now()->addDays(30)]);
        })->count();

        $operatingExpenses = VehicleExpense::whereBetween('expense_date', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->whereHas('category', fn($q) => $q->where('is_capital', false))
            ->sum('total_amount')
            + BusinessExpense::whereBetween('expense_date', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->whereHas('category', fn($q) => $q->where('is_capital', false))
            ->sum('total_amount');

        return response()->json([
            'today' => [
                'trip_count'  => $todayTripCount,
                'trip_revenue'=> round($todayTripRevenue, 2),
                'collected'   => round($todayCollected, 2),
                'expenses'    => round($todayExpenses, 2),
            ],
            'month' => [
                'revenue'          => round($monthRevenue, 2),
                'collected'        => round($monthCollected, 2),
                'expenses'         => round($monthExpenses, 2),
                'estimated_profit' => round($monthCollected - $operatingExpenses, 2),
            ],
            'outstanding_balance' => round($outstanding, 2),
            'expiry_alerts'       => $expiryAlerts,
        ]);
    }

    public function charts(Request $request)
    {
        $days = min((int) ($request->days ?? 30), 90);
        $from = now()->subDays($days - 1)->toDateString();
        $to   = now()->toDateString();

        $dailyRevenue = Trip::whereBetween('trip_date', [$from, $to])
            ->where('status', 'completed')
            ->groupBy('trip_date')->orderBy('trip_date')
            ->get(['trip_date', DB::raw('SUM(total_amount) as revenue'), DB::raw('SUM(amount_received) as collected')])
            ->map(fn($r) => ['date' => $r->trip_date->format('d M'), 'revenue' => (float) $r->revenue, 'collected' => (float) $r->collected]);

        $vehicleRevenue = Trip::whereBetween('trip_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->where('status', 'completed')
            ->with('vehicle:id,name,registration_number')
            ->groupBy('vehicle_id')
            ->get(['vehicle_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as trip_count')])
            ->map(fn($r) => ['vehicle' => $r->vehicle?->name ?? 'Unknown', 'revenue' => (float) $r->revenue, 'trips' => $r->trip_count]);

        $expenseBreakdown = VehicleExpense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->where('payment_status', 'paid')
            ->with('category:id,name')
            ->groupBy('category_id')
            ->get(['category_id', DB::raw('SUM(total_amount) as total')])
            ->map(fn($r) => ['category' => $r->category?->name ?? 'Other', 'total' => (float) $r->total]);

        return response()->json([
            'daily_revenue'     => $dailyRevenue,
            'vehicle_revenue'   => $vehicleRevenue,
            'expense_breakdown' => $expenseBreakdown,
        ]);
    }

    public function recentActivity()
    {
        $recentTrips = Trip::with(['vehicle:id,name,registration_number', 'customer:id,name,company_name,type'])
            ->where('status', 'completed')->orderByDesc('trip_date')->orderByDesc('id')->limit(5)
            ->get(['id', 'uuid', 'trip_number', 'trip_date', 'vehicle_id', 'customer_id', 'total_amount', 'payment_status']);

        $recentExpenses = VehicleExpense::with(['vehicle:id,name', 'category:id,name'])
            ->orderByDesc('expense_date')->orderByDesc('id')->limit(5)
            ->get(['id', 'uuid', 'expense_date', 'vehicle_id', 'category_id', 'total_amount', 'payment_status']);

        $expiringDocuments = Vehicle::where('is_active', true)->where(function ($q) {
            $q->whereBetween('insurance_expiry', [now(), now()->addDays(30)])
              ->orWhereBetween('permit_expiry',   [now(), now()->addDays(30)])
              ->orWhereBetween('fitness_expiry',  [now(), now()->addDays(30)])
              ->orWhereBetween('puc_expiry',      [now(), now()->addDays(30)]);
        })->get(['id', 'uuid', 'name', 'registration_number', 'insurance_expiry', 'permit_expiry', 'fitness_expiry', 'puc_expiry']);

        return response()->json([
            'recent_trips'       => $recentTrips,
            'recent_expenses'    => $recentExpenses,
            'expiring_documents' => $expiringDocuments,
        ]);
    }
}
