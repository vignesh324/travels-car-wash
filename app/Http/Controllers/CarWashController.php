<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\CarWashService;
use App\Models\CarWashTransaction;
use App\Models\CarWashTransactionItem;
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use App\Http\Traits\AjaxDataTableTrait;

class CarWashController extends Controller
{
    use AjaxDataTableTrait;

    public function addEdit(string $type, ?int $id = null)
    {
        abort_unless(in_array($type, ['transaction', 'service'], true), 404);
        $record = null;
        if ($id) {
            $record = $type === 'service'
                ? CarWashService::findOrFail($id)
                : CarWashTransaction::with('items')->findOrFail($id);
        }
        $pageTitle = ($record ? 'Edit ' : 'Add ') . ($type === 'service' ? 'Car Wash Service' : 'Car Wash Transaction');
        $formUrl = $type === 'service'
            ? ($record ? route('car-wash.services.update', $record->id) : route('car-wash.services.store'))
            : ($record ? route('car-wash.update', $record->id) : route('car-wash.store'));
        $customers = Customer::dropdownOptions(
            $type === 'transaction' ? $record?->customer_id : null
        );
        $serviceIds = $type === 'transaction' && $record
            ? $record->items->pluck('service_id')->filter()->all()
            : [];
        $services = CarWashService::dropdownOptions($serviceIds);
        $html = view('car-wash.add_edit', compact('type', 'record', 'formUrl', 'customers', 'services'))->render();

        return response()->json(compact('pageTitle', 'formUrl', 'html', 'type', 'record', 'customers', 'services'));
    }

    public function getData(Request $request)
    {
        $query = CarWashTransaction::with(['customer:id,name,company_name,type', 'items.service:id,name'])
            ->when($request->customer_id,    fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
            ->when($request->date_from,      fn($q, $v) => $q->whereDate('service_datetime', '>=', $v))
            ->when($request->date_to,        fn($q, $v) => $q->whereDate('service_datetime', '<=', $v));

        return $this->dataTableOf($query, function ($dataTable) {
            $dataTable
                ->addColumn('actions', fn (CarWashTransaction $transaction) =>
                    '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions">'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="car-wash.view" aria-label="' . e('View car wash transaction ' . $transaction->transaction_number) . '" title="View" data-transaction-id="' . e($transaction->id) . '"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="car-wash.edit" aria-label="' . e('Edit car wash transaction ' . $transaction->transaction_number) . '" title="Edit" data-transaction-id="' . e($transaction->id) . '"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="car-wash.download" aria-label="' . e('Download or print bill ' . $transaction->transaction_number) . '" title="Print" data-transaction-id="' . e($transaction->id) . '"><i class="bi bi-download" aria-hidden="true"></i><span>Print</span></button>'
                    . '<button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="car-wash.delete" aria-label="' . e('Delete car wash transaction ' . $transaction->transaction_number) . '" title="Delete" data-transaction-id="' . e($transaction->id) . '"><i class="bi bi-trash" aria-hidden="true"></i><span>Delete</span></button>'
                    . '</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function getServicesData()
    {
        return $this->dataTableOf(CarWashService::query(), function ($dataTable) {
            $dataTable
                ->addColumn('actions', fn (CarWashService $service) =>
                    '<div class="d-inline-flex justify-content-end gap-1 text-nowrap resource-row-actions">'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="car-wash.service-view" aria-label="' . e('View car wash service ' . $service->name) . '" title="View" data-view-url="' . e(route('car-wash.services.show', ['carWashService' => $service->id])) . '"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1 text-nowrap" data-action="car-wash.service-edit" aria-label="' . e('Edit car wash service ' . $service->name) . '" title="Edit" data-service-id="' . e($service->id) . '"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></button>'
                    . '</div>'
                )
                ->rawColumns(['actions']);
        });
    }

    public function index(Request $request)
    {
        $customers = Customer::dropdownOptions();
        $services = CarWashService::dropdownOptions();

        return view('car-wash.index', compact('customers', 'services'));
    }

    public function services(Request $request)
    {
        return response()->json(CarWashService::dropdownOptions());
    }

    public function showService(CarWashService $carWashService)
    {
        return response()->json($carWashService);
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

        $calc          = CarWashTransaction::calculateTotals($validated['items'], (float) ($validated['discount'] ?? 0));
        $amountPaid    = max(0, (float) ($validated['amount_paid'] ?? 0));
        $balance       = max(0, $calc['total_amount'] - $amountPaid);
        $paymentStatus = $amountPaid >= $calc['total_amount'] && $calc['total_amount'] > 0
            ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid');

        $tx = DB::transaction(function () use ($validated, $calc, $amountPaid, $balance, $paymentStatus) {
            $tx = CarWashTransaction::create([
                'transaction_number'   => $this->nextNumber(),
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
                ->withProperties(['total' => $tx->total_amount])
                ->log('Car wash transaction created');

            return $tx;
        });

        return response()->json($tx->load(['customer:id,name,company_name', 'items']), 201);
    }

    public function show(CarWashTransaction $carWash)
    {
        return response()->json($carWash->load(['customer', 'items.service']));
    }

    public function update(Request $request, CarWashTransaction $carWash)
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

        $calc          = CarWashTransaction::calculateTotals($validated['items'], (float) ($validated['discount'] ?? 0));
        $amountPaid    = max(0, (float) ($validated['amount_paid'] ?? 0));
        $balance       = max(0, $calc['total_amount'] - $amountPaid);
        $paymentStatus = $amountPaid >= $calc['total_amount'] && $calc['total_amount'] > 0
            ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid');

        DB::transaction(function () use ($carWash, $validated, $calc, $amountPaid, $balance, $paymentStatus) {
            $carWash->update([
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

            $carWash->items()->delete();
            foreach ($calc['items'] as $item) {
                CarWashTransactionItem::create(array_merge($item, ['transaction_id' => $carWash->id]));
            }
        });

        return response()->json($carWash->fresh()->load(['customer:id,name,company_name', 'items']));
    }

    public function destroy(CarWashTransaction $carWash)
    {
        $carWash->setDeletedBy();
        $carWash->delete();

        return response()->json(['message' => 'Transaction deleted']);
    }

    public function pdf(CarWashTransaction $carWash)
    {
        $carWash->load(['customer', 'items']);
        $profile  = BusinessProfile::first();
        $filename = 'Bill-' . str_replace('/', '-', $carWash->transaction_number) . '.pdf';

        return Pdf::loadView('pdf.carwash', compact('carWash', 'profile'))->download($filename);
    }

    public function pdfSignedUrl(CarWashTransaction $carWash)
    {
        $url = URL::temporarySignedRoute('pdf.carwash', now()->addMinutes(5), ['carWashTransaction' => $carWash->id]);

        return response()->json(['url' => $url]);
    }

    public function pdfSigned(CarWashTransaction $carWashTransaction)
    {
        $carWashTransaction->load(['customer', 'items']);
        $profile  = BusinessProfile::first();
        $filename = 'Bill-' . str_replace('/', '-', $carWashTransaction->transaction_number) . '.pdf';

        return Pdf::loadView('pdf.carwash', compact('carWashTransaction', 'profile'))->download($filename);
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
