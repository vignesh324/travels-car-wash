<?php

namespace Database\Seeders;

use App\Models\BusinessExpense;
use App\Models\BusinessProfile;
use App\Models\CarWashService;
use App\Models\CarWashTransaction;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $profile = BusinessProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => 'SKT Travels & Car Wash',
                'address' => 'No. 12, Demo Market Road, Gandhipuram, Coimbatore, Tamil Nadu 641012',
                'phone' => '+91 90000 00000',
                'email' => 'admin@example.com',
                'state' => 'Tamil Nadu',
                'state_code' => '33',
                'upi_id' => 'skttravels@example',
                'default_payment_terms' => 'Payment due within 30 days',
                'invoice_prefix' => 'INV',
                'receipt_prefix' => 'RCP',
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'date_format' => 'DD-MM-YYYY',
                'financial_year_start' => '04-01',
            ]
        );

        DB::transaction(function () use ($user, $profile) {
            $customers = $this->seedCustomers();
            $vehicles = $this->seedVehicles($user);
            $trips = $this->seedTrips($user, $customers, $vehicles);
            $invoices = $this->seedInvoices($user, $profile, $customers, $trips);
            $this->seedPayments($user, $customers, $invoices);
            $this->seedExpenses($user, $vehicles);
            $this->seedCarWashTransactions($user, $customers);
        });
    }

    private function seedCustomers(): array
    {
        $records = [
            [
                'name' => 'Kovai Valley Public School',
                'company_name' => 'Kovai Valley Public School',
                'contact_person' => 'Anita Sharma',
                'phone' => '9000000101',
                'email' => 'accounts@kovaivalley.example',
                'type' => 'corporate',
                'address' => 'Saibaba Colony, Coimbatore, Tamil Nadu',
                'billing_address' => 'Kovai Valley Public School, Saibaba Colony, Coimbatore',
                'state' => 'Tamil Nadu',
                'state_code' => '33',
                'payment_terms' => 'Net 30',
                'credit_limit' => 100000,
                'notes' => 'Regular school transport customer.',
            ],
            [
                'name' => 'Aarav Mehta',
                'company_name' => null,
                'contact_person' => null,
                'phone' => '9000000102',
                'email' => 'aarav.mehta@example.test',
                'type' => 'individual',
                'address' => 'RS Puram, Coimbatore, Tamil Nadu',
                'billing_address' => 'RS Puram, Coimbatore',
                'state' => 'Tamil Nadu',
                'state_code' => '33',
                'payment_terms' => 'Due on receipt',
                'credit_limit' => 15000,
                'notes' => 'Sample individual travel customer.',
            ],
            [
                'name' => 'Kongu Event Planners',
                'company_name' => 'Kongu Event Planners',
                'contact_person' => 'Arun Kumar',
                'phone' => '9000000103',
                'email' => 'bookings@konguevents.example',
                'type' => 'corporate',
                'address' => 'Peelamedu, Coimbatore, Tamil Nadu',
                'billing_address' => 'Kongu Event Planners, Peelamedu, Coimbatore',
                'state' => 'Tamil Nadu',
                'state_code' => '33',
                'payment_terms' => 'Net 15',
                'credit_limit' => 50000,
                'notes' => 'Sample corporate event transport customer.',
            ],
        ];

        $customers = [];
        foreach ($records as $record) {
            $phone = $record['phone'];
            $customers[$phone] = Customer::updateOrCreate(['phone' => $phone], $record);
        }

        return $customers;
    }

    private function seedVehicles(User $user): array
    {
        $records = [
            [
                'registration_number' => 'TN37DEMO01',
                'name' => 'School Bus 01',
                'model' => 'Starbus',
                'manufacturer' => 'Tata',
                'type' => 'Bus',
                'fuel_type' => 'Diesel',
                'seating_capacity' => 32,
                'ownership_type' => 'Owned',
                'default_driver_name' => 'Suresh Patil',
                'default_driver_phone' => '9000000201',
                'current_odometer' => 68420,
                'notes' => 'Sample school route vehicle.',
            ],
            [
                'registration_number' => 'TN37DEMO02',
                'name' => 'Traveller 01',
                'model' => 'Tempo Traveller',
                'manufacturer' => 'Force',
                'type' => 'Van',
                'fuel_type' => 'Diesel',
                'seating_capacity' => 17,
                'ownership_type' => 'Owned',
                'default_driver_name' => 'Mahesh Jadhav',
                'default_driver_phone' => '9000000202',
                'current_odometer' => 42350,
                'notes' => 'Sample corporate travel vehicle.',
            ],
            [
                'registration_number' => 'TN37DEMO03',
                'name' => 'Sedan 01',
                'model' => 'Dzire',
                'manufacturer' => 'Maruti Suzuki',
                'type' => 'Car',
                'fuel_type' => 'CNG',
                'seating_capacity' => 4,
                'ownership_type' => 'Attached',
                'default_driver_name' => 'Vijay More',
                'default_driver_phone' => '9000000203',
                'current_odometer' => 31800,
                'notes' => 'Sample local and airport trips.',
            ],
        ];

        $vehicles = [];
        foreach ($records as $record) {
            $registration = $record['registration_number'];
            $vehicles[$registration] = Vehicle::updateOrCreate(
                ['registration_number' => $registration],
                array_merge($record, ['created_by' => $user->id, 'updated_by' => $user->id])
            );
        }

        return $vehicles;
    }

    private function seedTrips(User $user, array $customers, array $vehicles): array
    {
        $records = [
            [
                'trip_number' => 'DEMO-TRIP-001',
                'days_ago' => 5,
                'vehicle' => 'TN37DEMO01',
                'customer' => '9000000101',
                'trip_type' => 'Corporate',
                'pickup_location' => 'Kovai Valley Public School',
                'drop_location' => 'School route stops',
                'route' => 'Saibaba Colony - Gandhipuram - School',
                'description' => 'Morning school transport',
                'start_odometer' => 68310,
                'end_odometer' => 68370,
                'fare_amount' => 6000,
                'status' => 'completed',
                'amount_received' => 0,
            ],
            [
                'trip_number' => 'DEMO-TRIP-002',
                'days_ago' => 4,
                'vehicle' => 'TN37DEMO01',
                'customer' => '9000000101',
                'trip_type' => 'Corporate',
                'pickup_location' => 'Kovai Valley Public School',
                'drop_location' => 'School route stops',
                'route' => 'Saibaba Colony - Gandhipuram - School',
                'description' => 'Morning school transport',
                'start_odometer' => 68370,
                'end_odometer' => 68430,
                'fare_amount' => 6000,
                'status' => 'completed',
                'amount_received' => 0,
            ],
            [
                'trip_number' => 'DEMO-TRIP-003',
                'days_ago' => 3,
                'vehicle' => 'TN37DEMO02',
                'customer' => '9000000103',
                'trip_type' => 'Outstation',
                'pickup_location' => 'Coimbatore Junction',
                'drop_location' => 'Ooty',
                'route' => 'Coimbatore - Mettupalayam - Ooty',
                'description' => 'Corporate event transfer',
                'start_odometer' => 42000,
                'end_odometer' => 42120,
                'fare_amount' => 4500,
                'status' => 'completed',
                'amount_received' => 0,
            ],
            [
                'trip_number' => 'DEMO-TRIP-004',
                'days_ago' => 2,
                'vehicle' => 'TN37DEMO01',
                'customer' => '9000000101',
                'trip_type' => 'Corporate',
                'pickup_location' => 'Kovai Valley Public School',
                'drop_location' => 'School route stops',
                'route' => 'Saibaba Colony - Gandhipuram - School',
                'description' => 'Afternoon school transport',
                'start_odometer' => 68430,
                'end_odometer' => 68490,
                'fare_amount' => 6000,
                'status' => 'completed',
                'amount_received' => 0,
            ],
            [
                'trip_number' => 'DEMO-TRIP-005',
                'days_ago' => 1,
                'vehicle' => 'TN37DEMO03',
                'customer' => '9000000102',
                'trip_type' => 'Local',
                'pickup_location' => 'RS Puram',
                'drop_location' => 'Coimbatore International Airport',
                'route' => 'RS Puram - Coimbatore Airport',
                'description' => 'Airport drop',
                'start_odometer' => 31730,
                'end_odometer' => 31800,
                'fare_amount' => 2200,
                'status' => 'completed',
                'amount_received' => 1000,
            ],
            [
                'trip_number' => 'DEMO-TRIP-006',
                'days_ago' => 0,
                'vehicle' => 'TN37DEMO02',
                'customer' => '9000000103',
                'trip_type' => 'Outstation',
                'pickup_location' => 'Gandhipuram, Coimbatore',
                'drop_location' => 'Valparai',
                'route' => 'Coimbatore - Pollachi - Valparai',
                'description' => 'Sample scheduled trip',
                'start_odometer' => null,
                'end_odometer' => null,
                'fare_amount' => 7500,
                'status' => 'draft',
                'amount_received' => 0,
            ],
        ];

        $trips = [];
        foreach ($records as $record) {
            $fare = $record['fare_amount'];
            $data = Trip::calculateAmounts([
                'trip_date' => now()->subDays($record['days_ago'])->toDateString(),
                'vehicle_id' => $vehicles[$record['vehicle']]->id,
                'customer_id' => $customers[$record['customer']]->id,
                'trip_type' => $record['trip_type'],
                'pickup_location' => $record['pickup_location'],
                'drop_location' => $record['drop_location'],
                'route' => $record['route'],
                'description' => $record['description'],
                'start_odometer' => $record['start_odometer'],
                'end_odometer' => $record['end_odometer'],
                'distance_km' => $record['start_odometer'] !== null
                    ? $record['end_odometer'] - $record['start_odometer']
                    : null,
                'driver_name' => $vehicles[$record['vehicle']]->default_driver_name,
                'fare_amount' => $fare,
                'additional_charges' => 0,
                'discount' => 0,
                'is_taxable' => false,
                'tax_rate' => 0,
                'amount_received' => $record['amount_received'],
                'status' => $record['status'],
                'payment_mode' => $record['amount_received'] > 0 ? 'UPI' : null,
                'is_invoiced' => false,
                'notes' => 'Demo data',
            ]);

            $tripNumber = $record['trip_number'];
            $trips[$tripNumber] = Trip::updateOrCreate(
                ['trip_number' => $tripNumber],
                array_merge($data, [
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ])
            );
        }

        return $trips;
    }

    private function seedInvoices(User $user, BusinessProfile $profile, array $customers, array $trips): array
    {
        $definitions = [
            [
                'invoice_number' => 'DEMO-INV-001',
                'customer' => '9000000101',
                'source' => 'travels',
                'trip_numbers' => ['DEMO-TRIP-001', 'DEMO-TRIP-002'],
                'items' => [[
                    'description' => 'School transport - Bus',
                    'source' => 'travels',
                    'vehicle_type' => 'Bus',
                    'route' => 'Saibaba Colony - Gandhipuram - School',
                    'service_days' => 2,
                    'quantity' => 1,
                    'unit_price' => 12000,
                    'discount' => 0,
                    'tax_rate' => 0,
                ]],
            ],
            [
                'invoice_number' => 'DEMO-INV-002',
                'customer' => '9000000103',
                'source' => 'travels',
                'trip_numbers' => ['DEMO-TRIP-003'],
                'items' => [[
                    'description' => 'Outstation transport - Van',
                    'source' => 'travels',
                    'vehicle_type' => 'Van',
                    'route' => 'Coimbatore - Mettupalayam - Ooty',
                    'service_days' => 1,
                    'quantity' => 1,
                    'unit_price' => 4500,
                    'discount' => 0,
                    'tax_rate' => 0,
                ]],
            ],
            [
                'invoice_number' => 'DEMO-INV-003',
                'customer' => '9000000101',
                'source' => 'travels',
                'trip_numbers' => ['DEMO-TRIP-004'],
                'items' => [[
                    'description' => 'School transport - Bus',
                    'source' => 'travels',
                    'vehicle_type' => 'Bus',
                    'route' => 'Saibaba Colony - Gandhipuram - School',
                    'service_days' => 1,
                    'quantity' => 1,
                    'unit_price' => 6000,
                    'discount' => 0,
                    'tax_rate' => 0,
                ]],
            ],
        ];

        $invoices = [];
        foreach ($definitions as $index => $definition) {
            $customer = $customers[$definition['customer']];
            $totals = Invoice::calculateTotals($definition['items'], $customer->state, $profile->state ?? $customer->state);
            $invoiceTotals = $totals;
            unset($invoiceTotals['items']);
            $invoice = Invoice::updateOrCreate(
                ['invoice_number' => $definition['invoice_number']],
                array_merge($invoiceTotals, [
                    'invoice_date' => now()->subDays(4 - $index)->toDateString(),
                    'due_date' => now()->addDays(26 - $index)->toDateString(),
                    'customer_id' => $customer->id,
                    'source' => $definition['source'],
                    'customer_name' => $customer->displayName(),
                    'customer_gstin' => $customer->gstin,
                    'customer_address' => $customer->billing_address ?: $customer->address,
                    'customer_state' => $customer->state,
                    'customer_state_code' => $customer->state_code,
                    'business_name' => $profile->business_name,
                    'business_gstin' => $profile->gstin,
                    'business_address' => $profile->address,
                    'bank_details_snapshot' => $profile->bank_details,
                    'place_of_supply' => $customer->state,
                    'amount_paid' => 0,
                    'balance_due' => $totals['total_amount'],
                    'payment_terms' => $customer->payment_terms,
                    'notes' => 'Demo invoice data',
                    'footer' => $profile->invoice_footer,
                    'status' => 'issued',
                    'issued_at' => now()->subDays(4 - $index),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ])
            );

            foreach ($totals['items'] as $itemIndex => $item) {
                InvoiceItem::updateOrCreate(
                    ['invoice_id' => $invoice->id, 'sort_order' => $itemIndex],
                    array_merge($item, [
                        'invoice_id' => $invoice->id,
                        'sort_order' => $itemIndex,
                    ])
                );
            }

            $invoice->tripLinks()->delete();
            foreach ($definition['trip_numbers'] as $tripNumber) {
                $trip = $trips[$tripNumber];
                $invoice->tripLinks()->firstOrCreate(['trip_id' => $trip->id]);
                $trip->update(['is_invoiced' => true]);
            }

            $invoices[$definition['invoice_number']] = $invoice;
        }

        return $invoices;
    }

    private function seedPayments(User $user, array $customers, array $invoices): void
    {
        $definitions = [
            [
                'payment_number' => 'DEMO-PAY-001',
                'invoice_number' => 'DEMO-INV-001',
                'customer' => '9000000101',
                'amount' => 3000,
                'method' => 'Bank Transfer',
                'days_ago' => 2,
            ],
            [
                'payment_number' => 'DEMO-PAY-002',
                'invoice_number' => 'DEMO-INV-002',
                'customer' => '9000000103',
                'amount' => 4500,
                'method' => 'UPI',
                'days_ago' => 1,
            ],
        ];

        foreach ($definitions as $definition) {
            $invoice = $invoices[$definition['invoice_number']];
            $payment = Payment::updateOrCreate(
                ['payment_number' => $definition['payment_number']],
                [
                    'customer_id' => $customers[$definition['customer']]->id,
                    'payment_date' => now()->subDays($definition['days_ago'])->toDateString(),
                    'amount' => $definition['amount'],
                    'allocated_amount' => $definition['amount'],
                    'unallocated_amount' => 0,
                    'payment_method' => $definition['method'],
                    'transaction_reference' => $definition['payment_number'],
                    'notes' => 'Demo payment data',
                    'status' => 'active',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            PaymentAllocation::updateOrCreate(
                ['payment_id' => $payment->id, 'invoice_id' => $invoice->id],
                [
                    'amount' => $definition['amount'],
                    'notes' => 'Demo invoice allocation',
                    'created_by' => $user->id,
                ]
            );

            $payment->recalculateAllocated();
            $invoice->recalculatePaymentStatus();
        }
    }

    private function seedExpenses(User $user, array $vehicles): void
    {
        $fuel = ExpenseCategory::where('name', 'Fuel')->where('type', 'vehicle')->firstOrFail();
        $maintenance = ExpenseCategory::where('name', 'Maintenance & Repairs')->where('type', 'vehicle')->firstOrFail();
        $rent = ExpenseCategory::where('name', 'Rent')->where('type', 'general')->firstOrFail();
        $supplies = ExpenseCategory::where('name', 'Cleaning Supplies')->where('type', 'carwash')->firstOrFail();

        $vehicleExpenses = [
            [
                'reference_number' => 'DEMO-VEH-EXP-001',
                'days_ago' => 6,
                'vehicle' => 'TN37DEMO01',
                'category_id' => $fuel->id,
                'description' => 'Diesel refill',
                'vendor_name' => 'Sample Fuel Station',
                'amount_before_tax' => 5000,
                'gst_amount' => 0,
                'total_amount' => 5000,
                'payment_mode' => 'UPI',
                'payment_status' => 'paid',
            ],
            [
                'reference_number' => 'DEMO-VEH-EXP-002',
                'days_ago' => 3,
                'vehicle' => 'TN37DEMO02',
                'category_id' => $maintenance->id,
                'description' => 'Scheduled vehicle service',
                'vendor_name' => 'Sample Auto Service',
                'amount_before_tax' => 3200,
                'gst_amount' => 576,
                'total_amount' => 3776,
                'payment_mode' => 'Cash',
                'payment_status' => 'paid',
            ],
        ];

        foreach ($vehicleExpenses as $expense) {
            $reference = $expense['reference_number'];
            $expenseData = $expense;
            unset($expenseData['days_ago'], $expenseData['vehicle']);
            VehicleExpense::updateOrCreate(
                ['reference_number' => $reference],
                array_merge($expenseData, [
                    'expense_date' => now()->subDays($expense['days_ago'])->toDateString(),
                    'vehicle_id' => $vehicles[$expense['vehicle']]->id,
                    'payment_date' => now()->subDays($expense['days_ago'])->toDateString(),
                    'notes' => 'Demo expense data',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ])
            );
        }

        $businessExpenses = [
            [
                'reference_number' => 'DEMO-BIZ-EXP-001',
                'days_ago' => 7,
                'category_id' => $rent->id,
                'source' => 'general',
                'description' => 'Sample office rent',
                'vendor_name' => 'Sample Property Services',
                'amount_before_tax' => 12000,
                'gst_amount' => 2160,
                'total_amount' => 14160,
                'payment_mode' => 'Bank Transfer',
                'payment_status' => 'paid',
            ],
            [
                'reference_number' => 'DEMO-BIZ-EXP-002',
                'days_ago' => 2,
                'category_id' => $supplies->id,
                'source' => 'carwash',
                'description' => 'Wash shampoo and microfiber cloths',
                'vendor_name' => 'Sample Cleaning Supplies',
                'amount_before_tax' => 1800,
                'gst_amount' => 324,
                'total_amount' => 2124,
                'payment_mode' => 'UPI',
                'payment_status' => 'unpaid',
            ],
        ];

        foreach ($businessExpenses as $expense) {
            $reference = $expense['reference_number'];
            $expenseData = $expense;
            unset($expenseData['days_ago']);
            BusinessExpense::updateOrCreate(
                ['reference_number' => $reference],
                array_merge($expenseData, [
                    'expense_date' => now()->subDays($expense['days_ago'])->toDateString(),
                    'payment_date' => $expense['payment_status'] === 'paid'
                        ? now()->subDays($expense['days_ago'])->toDateString()
                        : null,
                    'notes' => 'Demo expense data',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ])
            );
        }
    }

    private function seedCarWashTransactions(User $user, array $customers): void
    {
        $services = CarWashService::whereIn('name', [
            'Basic Wash',
            'Foam Wash',
            'Interior Cleaning',
            'Full Car Detailing',
        ])
            ->get()
            ->keyBy('name');

        foreach (['Basic Wash', 'Foam Wash', 'Interior Cleaning', 'Full Car Detailing'] as $name) {
            if (! $services->has($name)) {
                throw new \RuntimeException("Required demo car wash service [{$name}] is missing.");
            }
        }

        $definitions = [
            [
                'transaction_number' => 'DEMO-WASH-001',
                'days_ago' => 4,
                'customer' => '9000000102',
                'vehicle_registration' => 'TN37DEMO03',
                'items' => ['Basic Wash', 'Interior Cleaning'],
                'amount_paid' => 450,
                'payment_mode' => 'UPI',
            ],
            [
                'transaction_number' => 'DEMO-WASH-002',
                'days_ago' => 2,
                'customer' => null,
                'vehicle_registration' => 'TN37DEMO01',
                'items' => ['Full Car Detailing'],
                'amount_paid' => 0,
                'payment_mode' => null,
            ],
            [
                'transaction_number' => 'DEMO-WASH-003',
                'days_ago' => 0,
                'customer' => '9000000103',
                'vehicle_registration' => 'TN37DEMO02',
                'items' => ['Foam Wash'],
                'amount_paid' => 0,
                'payment_mode' => null,
            ],
        ];

        foreach ($definitions as $definition) {
            $items = [];
            foreach ($definition['items'] as $name) {
                $service = $services->get($name);
                $items[] = [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'quantity' => 1,
                    'unit_price' => (float) $service->default_price,
                    'discount' => 0,
                    'tax_rate' => (float) $service->tax_rate,
                ];
            }

            $totals = CarWashTransaction::calculateTotals($items);
            $transactionTotals = $totals;
            unset($transactionTotals['items']);
            $paid = min((float) $definition['amount_paid'], $totals['total_amount']);
            $transaction = CarWashTransaction::updateOrCreate(
                ['transaction_number' => $definition['transaction_number']],
                array_merge($transactionTotals, [
                    'service_datetime' => now()->subDays($definition['days_ago'])->setTime(10, 30),
                    'customer_id' => $definition['customer']
                        ? $customers[$definition['customer']]->id
                        : null,
                    'vehicle_registration' => $definition['vehicle_registration'],
                    'amount_paid' => $paid,
                    'balance_due' => max(0, $totals['total_amount'] - $paid),
                    'payment_mode' => $definition['payment_mode'],
                    'payment_status' => $paid >= $totals['total_amount']
                        ? 'paid'
                        : ($paid > 0 ? 'partial' : 'unpaid'),
                    'notes' => 'Demo car wash transaction',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ])
            );

            foreach ($totals['items'] as $index => $item) {
                $transaction->items()->updateOrCreate(
                    ['service_id' => $item['service_id'], 'service_name' => $item['service_name']],
                    $item
                );
            }
        }
    }
}
