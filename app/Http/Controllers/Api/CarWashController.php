<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarWashService;
use App\Models\CarWashTransaction;
use App\Models\CarWashTransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CarWashController extends Controller
{
    public function services(Request $request)
    {
        return response()->json(
            CarWashService::where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function storeService(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string',
            'default_price' => 'required|numeric|min:0',
            'tax_rate'      => 'nullable|numeric|min:0|max:100',
            'is_active'     => 'boolean',
            'sort_order'    => 'nullable|integer|min:0',
        ]);
        return response()->json(CarWashService::create($data), 201);
    }

    public function updateService(Request $request, CarWashService $carWashService)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string',
            'default_price' => 'required|numeric|min:0',
            'tax_rate'      => 'nullable|numeric|min:0|max:100',
            'is_active'     => 'boolean',
            'sort_order'    => 'nullable|integer|min:0',
        ]);
        $carWashService->update($data);
        return response()->json($carWashService->fresh());
    }

    public function index(Request $request)
    {
        if ($request->boolean('datatable')) {
            $query = CarWashTransaction::with(['customer:id,name,company_name,type', 'items'])
                ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
                ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
                ->when($request->date_from, fn($q, $v) => $q->whereDate('service_datetime', '>=', $v))
                ->when($request->date_to, fn($q, $v) => $q->whereDate('service_datetime', '<=', $v));
            return DataTables::of($query)->make(true);
        }
        return response()->json([]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_datetime'     => 'required|date',
            'customer_id'          => 'nullable|exists:customers,id',
            'vehicle_registration' => 'nullable|string|max:20',
            'discount'             => 'nullable|numeric|min:0',
            'payment_mode'         => 'nullable|string|max:50',
            'amount_paid'          => 'nullable|numeric|min:0',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.service_id'   => 'nullable|exists:car_wash_services,id',
            'items.*.service_name' => 'required|string|max:100',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'items.*.tax_rate'     => 'nullable|numeric|min:0|max:100',
        ]);

        $calc = CarWashTransaction::calculateTotals($validated['items'], (float)($validated['discount'] ?? 0));
        $amountPaid = max(0, (float)($validated['amount_paid'] ?? 0));
        $balance = max(0, $calc['total_amount'] - $amountPaid);
        $paymentStatus = $amountPaid >= $calc['total_amount'] && $calc['total_amount'] > 0
            ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid');

        $tx = DB::transaction(function () use ($validated, $calc, $amountPaid, $balance, $paymentStatus) {
            $number = $this->nextNumber();
            $tx = CarWashTransaction::create([
                'transaction_number'   => $number,
                'service_datetime'     => $validated['service_datetime'],
                'customer_id'          => $validated['customer_id'] ?? null,
                'vehicle_registration' => $validated['vehicle_registration'] ?? null,
                'subtotal'             => $calc['subtotal'],
                'discount'             => $calc['discount'],
                'taxable_amount'       => $calc['taxable_amount'],
                'tax_amount'           => $calc['tax_amount'],
                'total_amount'         => $calc['total_amount'],
                'amount_paid'          => $amountPaid,
                'balance_due'          => $balance,
                'payment_mode'         => $validated['payment_mode'] ?? null,
                'payment_status'       => $paymentStatus,
                'notes'                => $validated['notes'] ?? null,
            ]);
            foreach ($calc['items'] as $item) {
                CarWashTransactionItem::create(array_merge($item, ['transaction_id' => $tx->id]));
            }
            activity()->causedBy(Auth::user())->performedOn($tx)
                ->withProperties(['total' => $tx->total_amount])->log('Car wash transaction created');
            return $tx;
        });

        return response()->json($tx->load(['customer:id,name,company_name', 'items']), 201);
    }

    public function show(CarWashTransaction $carWashTransaction)
    {
        return response()->json($carWashTransaction->load(['customer', 'items.service']));
    }

    public function update(Request $request, CarWashTransaction $carWashTransaction)
    {
        $validated = $request->validate([
            'service_datetime'     => 'required|date',
            'customer_id'          => 'nullable|exists:customers,id',
            'vehicle_registration' => 'nullable|string|max:20',
            'discount'             => 'nullable|numeric|min:0',
            'payment_mode'         => 'nullable|string|max:50',
            'amount_paid'          => 'nullable|numeric|min:0',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.service_id'   => 'nullable|exists:car_wash_services,id',
            'items.*.service_name' => 'required|string|max:100',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'items.*.tax_rate'     => 'nullable|numeric|min:0|max:100',
        ]);

        $calc = CarWashTransaction::calculateTotals($validated['items'], (float)($validated['discount'] ?? 0));
        $amountPaid = max(0, (float)($validated['amount_paid'] ?? 0));
        $balance = max(0, $calc['total_amount'] - $amountPaid);
        $paymentStatus = $amountPaid >= $calc['total_amount'] && $calc['total_amount'] > 0
            ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid');

        DB::transaction(function () use ($carWashTransaction, $validated, $calc, $amountPaid, $balance, $paymentStatus) {
            $carWashTransaction->update([
                'service_datetime'     => $validated['service_datetime'],
                'customer_id'          => $validated['customer_id'] ?? null,
                'vehicle_registration' => $validated['vehicle_registration'] ?? null,
                'subtotal'             => $calc['subtotal'],
                'discount'             => $calc['discount'],
                'taxable_amount'       => $calc['taxable_amount'],
                'tax_amount'           => $calc['tax_amount'],
                'total_amount'         => $calc['total_amount'],
                'amount_paid'          => $amountPaid,
                'balance_due'          => $balance,
                'payment_mode'         => $validated['payment_mode'] ?? null,
                'payment_status'       => $paymentStatus,
                'notes'                => $validated['notes'] ?? null,
            ]);
            $carWashTransaction->items()->delete();
            foreach ($calc['items'] as $item) {
                CarWashTransactionItem::create(array_merge($item, ['transaction_id' => $carWashTransaction->id]));
            }
        });

        return response()->json($carWashTransaction->fresh()->load(['customer:id,name,company_name', 'items']));
    }

    public function destroy(CarWashTransaction $carWashTransaction)
    {
        $carWashTransaction->setDeletedBy();
        $carWashTransaction->delete();
        return response()->json(['message' => 'Transaction deleted']);
    }

    private function nextNumber(): string
    {
        $seq = DB::table('invoice_sequences')
            ->where('prefix', 'CW')->where('financial_year', '')->lockForUpdate()->first();
        if ($seq) {
            $next = $seq->last_number + 1;
            DB::table('invoice_sequences')->where('id', $seq->id)->update(['last_number' => $next, 'updated_at' => now()]);
        } else {
            $next = 1;
            DB::table('invoice_sequences')->insert(['prefix' => 'CW', 'financial_year' => '', 'last_number' => $next, 'created_at' => now(), 'updated_at' => now()]);
        }
        return 'CW/' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
