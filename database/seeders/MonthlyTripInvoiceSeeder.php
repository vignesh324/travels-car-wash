<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MonthlyTripInvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $profile = BusinessProfile::firstOrFail();
        $month = now()->subMonthNoOverflow()->startOfMonth();
        $invoiceNumber = 'DEMO-MONTHLY-' . $month->format('Ym');

        if (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
            $this->command?->info("Monthly demo invoice {$invoiceNumber} already exists; no changes made.");
            return;
        }

        DB::transaction(function () use ($profile, $month, $invoiceNumber) {
            $customer = Customer::updateOrCreate(
                ['phone' => '9000000199'],
                [
                    'type' => 'corporate',
                    'name' => 'Kovai Valley Accounts',
                    'company_name' => 'Kovai Valley Industries',
                    'contact_person' => 'Accounts Department',
                    'email' => 'accounts@kovaivalley.example',
                    'address' => 'Peelamedu, Coimbatore, Tamil Nadu',
                    'billing_address' => 'Kovai Valley Industries, Peelamedu, Coimbatore',
                    'state' => $profile->state ?: 'Tamil Nadu',
                    'state_code' => $profile->state_code,
                    'payment_terms' => 'Due within 15 days',
                    'is_active' => true,
                ]
            );

            $vehicles = collect([
                [
                    'registration_number' => 'TN37MON001',
                    'name' => 'Monthly Corporate Van',
                    'model' => 'Traveller',
                    'type' => 'Van',
                    'default_driver_name' => 'Suresh Kumar',
                ],
                [
                    'registration_number' => 'TN37MON002',
                    'name' => 'Monthly Corporate Sedan',
                    'model' => 'Sedan',
                    'type' => 'Car',
                    'default_driver_name' => 'Ravi Kumar',
                ],
            ])->mapWithKeys(function (array $attributes) {
                $registrationNumber = $attributes['registration_number'];
                $vehicle = Vehicle::updateOrCreate(
                    ['registration_number' => $registrationNumber],
                    $attributes + [
                        'fuel_type' => 'Diesel',
                        'ownership_type' => 'Owned',
                        'is_active' => true,
                    ]
                );

                return [$registrationNumber => $vehicle];
            });

            $serviceDates = collect();
            $date = $month->copy();
            while ($date->month === $month->month) {
                if ($date->isWeekday()) {
                    $serviceDates->push($date->copy());
                }
                $date->addDay();
            }

            $trips = $serviceDates->values()->map(function (Carbon $serviceDate, int $index) use ($customer, $vehicles, $month) {
                $vehicle = $vehicles[$index % 2 === 0 ? 'TN37MON001' : 'TN37MON002'];
                $tripNumber = sprintf('DEMO-MONTHLY-%s-%02d', $month->format('Ym'), $index + 1);
                $fare = $vehicle->registration_number === 'TN37MON001' ? 1800 : 1500;
                $amounts = Trip::calculateAmounts([
                    'fare_amount' => $fare,
                    'additional_charges' => 0,
                    'discount' => 0,
                    'is_taxable' => false,
                    'tax_rate' => 0,
                ]);

                return Trip::updateOrCreate(
                    ['trip_number' => $tripNumber],
                    array_merge($amounts, [
                        'trip_date' => $serviceDate->toDateString(),
                        'vehicle_id' => $vehicle->id,
                        'customer_id' => $customer->id,
                        'trip_type' => 'Corporate',
                        'pickup_location' => 'Kovai Valley Industries',
                        'drop_location' => 'Coimbatore City',
                        'route' => 'Corporate monthly service',
                        'driver_name' => $vehicle->default_driver_name,
                        'payment_mode' => 'Credit',
                        'status' => 'completed',
                        'is_invoiced' => false,
                    ])
                );
            });

            $tripCollection = Trip::with('vehicle')->whereIn('id', $trips->pluck('id'))->get();
            $tripItems = Invoice::tripSummaryItems($tripCollection);
            $totals = Invoice::calculateTotals($tripItems, $profile->state ?: '', $profile->state ?: '');
            $processedItems = $totals['items'];
            unset($totals['items']);
            $invoiceDate = $month->copy()->endOfMonth();

            $invoice = Invoice::create(array_merge($totals, [
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $invoiceDate->copy()->addDays(15)->toDateString(),
                'customer_id' => $customer->id,
                'source' => 'travels',
                'customer_name' => $customer->displayName(),
                'customer_gstin' => $customer->gstin,
                'customer_address' => $customer->billing_address ?: $customer->address,
                'customer_state' => $customer->state,
                'customer_state_code' => $customer->state_code,
                'business_name' => $profile->business_name,
                'business_gstin' => $profile->gstin,
                'business_address' => $profile->address,
                'bank_details_snapshot' => $profile->bank_details,
                'place_of_supply' => $profile->state,
                'amount_paid' => 0,
                'balance_due' => $totals['total_amount'],
                'payment_terms' => 'Payment due within 15 days of invoice date.',
                'notes' => 'Sample monthly invoice generated from completed corporate trips.',
                'footer' => $profile->invoice_footer,
                'status' => 'issued',
                'issued_at' => now(),
            ]));

            foreach ($processedItems as $item) {
                InvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
            }

            foreach ($tripCollection as $trip) {
                $invoice->tripLinks()->create(['trip_id' => $trip->id]);
            }

            Trip::whereIn('id', $tripCollection->pluck('id'))->update(['is_invoiced' => true]);
        });

        $this->command?->info("Created monthly demo invoice {$invoiceNumber}.");
    }
}
