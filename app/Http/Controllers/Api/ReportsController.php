<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessExpense;
use App\Models\CarWashTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\VehicleExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportsController extends Controller
{
    private function dateRange(Request $request): array
    {
        $from = $request->date_from ?? now()->startOfMonth()->toDateString();
        $to   = $request->date_to   ?? now()->toDateString();
        return [$from, $to];
    }

    public function revenue(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $trips = Trip::whereBetween('trip_date', [$from, $to])
            ->where('status', 'completed')
            ->selectRaw('trip_date as date, SUM(total_amount) as revenue, SUM(amount_received) as collected, COUNT(*) as count')
            ->groupBy('trip_date')
            ->orderBy('trip_date')
            ->get();

        $carwash = CarWashTransaction::whereBetween(DB::raw('DATE(service_datetime)'), [$from, $to])
            ->selectRaw('DATE(service_datetime) as date, SUM(total_amount) as revenue, SUM(amount_paid) as collected, COUNT(*) as count')
            ->groupBy(DB::raw('DATE(service_datetime)'))
            ->orderBy('date')
            ->get();

        $tripTotal    = Trip::whereBetween('trip_date', [$from, $to])->where('status', 'completed')->sum('total_amount');
        $tripCollected = Trip::whereBetween('trip_date', [$from, $to])->where('status', 'completed')->sum('amount_received');
        $cwTotal      = CarWashTransaction::whereBetween(DB::raw('DATE(service_datetime)'), [$from, $to])->sum('total_amount');
        $cwCollected  = CarWashTransaction::whereBetween(DB::raw('DATE(service_datetime)'), [$from, $to])->sum('amount_paid');

        return response()->json([
            'summary' => [
                'travels_revenue'   => round($tripTotal, 2),
                'travels_collected' => round($tripCollected, 2),
                'carwash_revenue'   => round($cwTotal, 2),
                'carwash_collected' => round($cwCollected, 2),
                'total_revenue'     => round($tripTotal + $cwTotal, 2),
                'total_collected'   => round($tripCollected + $cwCollected, 2),
            ],
            'trips'   => $trips,
            'carwash' => $carwash,
        ]);
    }

    public function expenses(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $vehicle = VehicleExpense::whereBetween('expense_date', [$from, $to])
            ->with(['vehicle:id,name,registration_number', 'category:id,name'])
            ->when($request->vehicle_id, fn($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->orderByDesc('expense_date')
            ->get();

        $business = BusinessExpense::whereBetween('expense_date', [$from, $to])
            ->with('category:id,name')
            ->when($request->source, fn($q, $v) => $q->where('source', $v))
            ->orderByDesc('expense_date')
            ->get();

        $vehicleTotal   = $vehicle->sum('total_amount');
        $businessTotal  = $business->sum('total_amount');

        $byCategory = $vehicle->groupBy('category.name')->map(fn($g) => $g->sum('total_amount'));
        $byVehicle  = $vehicle->groupBy('vehicle.name')->map(fn($g) => $g->sum('total_amount'));

        return response()->json([
            'summary' => [
                'vehicle_expenses'  => round($vehicleTotal, 2),
                'business_expenses' => round($businessTotal, 2),
                'total_expenses'    => round($vehicleTotal + $businessTotal, 2),
            ],
            'by_category' => $byCategory,
            'by_vehicle'  => $byVehicle,
            'vehicle_expenses'  => $vehicle,
            'business_expenses' => $business,
        ]);
    }

    public function trips(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = Trip::whereBetween('trip_date', [$from, $to])
            ->with(['vehicle:id,name,registration_number', 'customer:id,name,company_name,type'])
            ->when($request->vehicle_id, fn($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->trip_type, fn($q, $v) => $q->where('trip_type', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
            ->orderByDesc('trip_date');

        $trips = $query->paginate(50);

        $summary = Trip::whereBetween('trip_date', [$from, $to])
            ->when($request->vehicle_id, fn($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue, SUM(amount_received) as collected, SUM(balance_due) as outstanding')
            ->first();

        return response()->json(['summary' => $summary, 'trips' => $trips]);
    }

    public function invoices(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = Invoice::whereBetween('invoice_date', [$from, $to])
            ->with('customer:id,name,company_name')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
            ->orderByDesc('invoice_date');

        $invoices = $query->paginate(50);

        $summary = Invoice::whereBetween('invoice_date', [$from, $to])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as invoiced, SUM(amount_paid) as collected, SUM(balance_due) as outstanding')
            ->first();

        return response()->json(['summary' => $summary, 'invoices' => $invoices]);
    }

    public function payments(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = Payment::whereBetween('payment_date', [$from, $to])
            ->with('customer:id,name,company_name')
            ->where('status', 'active')
            ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->payment_method, fn($q, $v) => $q->where('payment_method', $v))
            ->orderByDesc('payment_date');

        $payments = $query->paginate(50);

        $byMethod = Payment::whereBetween('payment_date', [$from, $to])
            ->where('status', 'active')
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get();

        $total = Payment::whereBetween('payment_date', [$from, $to])->where('status', 'active')->sum('amount');

        return response()->json([
            'summary'   => ['total_collected' => round($total, 2)],
            'by_method' => $byMethod,
            'payments'  => $payments,
        ]);
    }

    public function carwash(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = CarWashTransaction::whereBetween(DB::raw('DATE(service_datetime)'), [$from, $to])
            ->with(['customer:id,name,company_name', 'items'])
            ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
            ->orderByDesc('service_datetime');

        $transactions = $query->paginate(50);

        $summary = CarWashTransaction::whereBetween(DB::raw('DATE(service_datetime)'), [$from, $to])
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue, SUM(amount_paid) as collected, SUM(balance_due) as outstanding')
            ->first();

        return response()->json(['summary' => $summary, 'transactions' => $transactions]);
    }

    public function exportTrips(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $trips = Trip::whereBetween('trip_date', [$from, $to])
            ->with(['vehicle:id,name,registration_number', 'customer:id,name,company_name'])
            ->when($request->vehicle_id, fn($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->orderByDesc('trip_date')
            ->get();

        $export = new class($trips) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $data) {}
            public function collection(): Enumerable { return $this->data; }
            public function headings(): array {
                return ['Trip #', 'Date', 'Vehicle', 'Customer', 'Type', 'Pickup', 'Drop', 'Fare', 'Total', 'Received', 'Balance', 'Status', 'Payment Status'];
            }
            public function map($row): array {
                return [
                    $row->trip_number,
                    $row->trip_date->format('d-m-Y'),
                    $row->vehicle?->name ?? '',
                    $row->customer?->name ?? $row->customer?->company_name ?? 'Walk-in',
                    $row->trip_type,
                    $row->pickup_location ?? '',
                    $row->drop_location ?? '',
                    $row->fare_amount,
                    $row->total_amount,
                    $row->amount_received,
                    $row->balance_due,
                    $row->status,
                    $row->payment_status,
                ];
            }
        };

        return Excel::download($export, "trips-{$from}-to-{$to}.xlsx");
    }

    public function exportExpenses(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $expenses = VehicleExpense::whereBetween('expense_date', [$from, $to])
            ->with(['vehicle:id,name', 'category:id,name'])
            ->when($request->vehicle_id, fn($q, $v) => $q->where('vehicle_id', $v))
            ->orderByDesc('expense_date')
            ->get();

        $export = new class($expenses) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $data) {}
            public function collection(): Enumerable { return $this->data; }
            public function headings(): array {
                return ['Date', 'Vehicle', 'Category', 'Description', 'Vendor', 'Amount', 'GST', 'Total', 'Payment Mode', 'Status'];
            }
            public function map($row): array {
                return [
                    $row->expense_date,
                    $row->vehicle?->name ?? '',
                    $row->category?->name ?? '',
                    $row->description ?? '',
                    $row->vendor_name ?? '',
                    $row->amount_before_tax,
                    $row->gst_amount,
                    $row->total_amount,
                    $row->payment_mode,
                    $row->payment_status,
                ];
            }
        };

        return Excel::download($export, "expenses-{$from}-to-{$to}.xlsx");
    }

    public function exportInvoices(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $invoices = Invoice::whereBetween('invoice_date', [$from, $to])
            ->with('customer:id,name,company_name')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->orderByDesc('invoice_date')
            ->get();

        $export = new class($invoices) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $data) {}
            public function collection(): Enumerable { return $this->data; }
            public function headings(): array {
                return ['Invoice #', 'Date', 'Due Date', 'Customer', 'Taxable', 'CGST', 'SGST', 'IGST', 'Total', 'Paid', 'Balance', 'Status'];
            }
            public function map($row): array {
                return [
                    $row->invoice_number,
                    $row->invoice_date->format('d-m-Y'),
                    $row->due_date?->format('d-m-Y') ?? '',
                    $row->customer_name,
                    $row->taxable_amount,
                    $row->cgst_amount,
                    $row->sgst_amount,
                    $row->igst_amount,
                    $row->total_amount,
                    $row->amount_paid,
                    $row->balance_due,
                    $row->status,
                ];
            }
        };

        return Excel::download($export, "invoices-{$from}-to-{$to}.xlsx");
    }
}
