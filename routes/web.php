<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CarWashController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\QuickTripController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


Route::get('/migrate', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);
    $migrateOutput = Artisan::output();

    Artisan::call('db:seed', ['--force' => true]);
    $seedOutput = Artisan::output();

    return response()->json([
        'migrate' => $migrateOutput,
        'seed'    => $seedOutput,
    ]);
});

Route::get('/cache-clear', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    return response()->json(['message' => 'Cache cleared']);
});
// ── Auth (laravel/ui) ─────────────────────────────────────────────────────────
Auth::routes(['register' => false, 'reset' => false, 'verify' => false]);

// ── Public signed PDF routes ──────────────────────────────────────────────────
Route::get('/pdf/invoice/{invoice}',            [InvoiceController::class,  'pdfSigned'])->name('pdf.invoice')->middleware('signed');
Route::get('/pdf/receipt/{payment}',            [PaymentController::class,  'receiptSigned'])->name('pdf.receipt')->middleware('signed');
Route::get('/pdf/carwash/{carWashTransaction}', [CarWashController::class,  'pdfSigned'])->name('pdf.carwash')->middleware('signed');

// ── Authenticated routes ──────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/', fn() => redirect()->route('dashboard.index'));

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Vehicles
    Route::get('vehicles/add-edit/{uuid?}', [VehicleController::class, 'addEdit'])->name('vehicles.addEdit');
    Route::get('vehicles/getData', [VehicleController::class, 'getData'])->name('vehicles.getData');
    Route::resource('vehicles', VehicleController::class);
    Route::post('vehicles/{uuid}/restore', [VehicleController::class, 'restore'])->name('vehicles.restore');

    // Customers
    Route::get('customers/add-edit/{uuid?}', [CustomerController::class, 'addEdit'])->name('customers.addEdit');
    Route::get('customers/getData', [CustomerController::class, 'getData'])->name('customers.getData');
    Route::get('customers/quick-trips/add-edit/{configuration?}', [QuickTripController::class, 'configurationAddEdit'])->name('quick-trip-configurations.addEdit');
    Route::get('customers/quick-trips/getData', [QuickTripController::class, 'configurationData'])->name('quick-trip-configurations.getData');
    Route::get('customers/quick-trips', [QuickTripController::class, 'configurations'])->name('customers.quick-trips');
    Route::get('quick-trip-configurations/{configuration}', [QuickTripController::class, 'showConfiguration'])->name('quick-trip-configurations.show');
    Route::post('quick-trip-configurations', [QuickTripController::class, 'storeConfiguration'])->name('quick-trip-configurations.store');
    Route::put('quick-trip-configurations/{configuration}', [QuickTripController::class, 'updateConfiguration'])->name('quick-trip-configurations.update');
    Route::post('quick-trip-configurations/{configuration}/toggle-status', [QuickTripController::class, 'toggleConfiguration'])->name('quick-trip-configurations.toggle-status');
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{uuid}/restore',          [CustomerController::class, 'restore'])->name('customers.restore');
    Route::get('customers/{customer}/statement',     [CustomerController::class, 'statement'])->name('customers.statement');

    // Trips
    Route::get('quick-trip-executions', [QuickTripController::class, 'executionIndex'])->name('quick-trip-executions.index');
    Route::get('quick-trip-executions/getData', [QuickTripController::class, 'executionData'])->name('quick-trip-executions.getData');
    Route::get('trips/quick-execution', [QuickTripController::class, 'execution'])->name('trips.quick-execution');
    Route::get('trips/quick-execution/configurations', [QuickTripController::class, 'activeConfigurations'])->name('trips.quick-execution.configurations');
    Route::post('trips/quick-execution/generate', [QuickTripController::class, 'generate'])->name('trips.quick-execution.generate');
    Route::get('trips/add-edit/{uuid?}', [TripController::class, 'addEdit'])->name('trips.addEdit');
    Route::get('trips/getData', [TripController::class, 'getData'])->name('trips.getData');
    Route::resource('trips', TripController::class);
    Route::get('trips/summary',            [TripController::class, 'summary'])->name('trips.summary');

    // Expenses (vehicle + business + categories unified)
    Route::get('expenses/{type}/add-edit/{id?}', [ExpenseController::class, 'addEdit'])->name('expenses.addEdit');
    Route::get('expenses/getData',        [ExpenseController::class, 'getData'])->name('expenses.getData');
    Route::get('expenses/getVehicleData',  [ExpenseController::class, 'getVehicleData'])->name('expenses.getVehicleData');
    Route::get('expenses/getBusinessData', [ExpenseController::class, 'getBusinessData'])->name('expenses.getBusinessData');
    Route::get('expenses/vehicle', [ExpenseController::class, 'vehicleIndex'])->name('expenses.vehicle.index');
    Route::get('expenses/business', [ExpenseController::class, 'businessIndex'])->name('expenses.business.index');
    Route::get('expenses',                                          [ExpenseController::class, 'index'])->name('expenses.index');
    // Vehicle expenses
    Route::post('expenses/vehicle',                                 [ExpenseController::class, 'storeVehicle'])->name('expenses.vehicle.store');
    Route::get('expenses/vehicle/{vehicleExpense}',                 [ExpenseController::class, 'showVehicle'])->name('expenses.vehicle.show');
    Route::put('expenses/vehicle/{vehicleExpense}',                 [ExpenseController::class, 'updateVehicle'])->name('expenses.vehicle.update');
    Route::delete('expenses/vehicle/{vehicleExpense}',              [ExpenseController::class, 'destroyVehicle'])->name('expenses.vehicle.destroy');
    // Business expenses
    Route::post('expenses/business',                                [ExpenseController::class, 'storeBusiness'])->name('expenses.business.store');
    Route::get('expenses/business/{businessExpense}',               [ExpenseController::class, 'showBusiness'])->name('expenses.business.show');
    Route::put('expenses/business/{businessExpense}',               [ExpenseController::class, 'updateBusiness'])->name('expenses.business.update');
    Route::delete('expenses/business/{businessExpense}',            [ExpenseController::class, 'destroyBusiness'])->name('expenses.business.destroy');
    // Expense categories
    Route::post('expenses/categories',                              [ExpenseController::class, 'storeCategory'])->name('expenses.categories.store');
    Route::put('expenses/categories/{expenseCategory}',             [ExpenseController::class, 'updateCategory'])->name('expenses.categories.update');

    // Invoices
    Route::get('invoices/add-edit/{uuid?}', [InvoiceController::class, 'addEdit'])->name('invoices.addEdit');
    Route::get('invoices/getData', [InvoiceController::class, 'getData'])->name('invoices.getData');
    Route::get('invoices/eligible-trips', [InvoiceController::class, 'eligibleTrips'])->name('invoices.eligible-trips');
    Route::get('invoices/eligible-quick-trips', [InvoiceController::class, 'eligibleQuickTrips'])->name('invoices.eligible-quick-trips');
    Route::resource('invoices', InvoiceController::class)->except(['destroy']);
    Route::post('invoices/{invoice}/cancel',   [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::get('invoices/{invoice}/pdf',       [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/pdf-url',   [InvoiceController::class, 'pdfSignedUrl'])->name('invoices.pdf-url');

    // Payments
    Route::get('payments/add-edit', [PaymentController::class, 'addEdit'])->name('payments.addEdit');
    Route::get('payments/getData', [PaymentController::class, 'getData'])->name('payments.getData');
    Route::resource('payments', PaymentController::class)->except(['destroy']);
    Route::post('payments/{payment}/allocate',    [PaymentController::class, 'allocate'])->name('payments.allocate');
    Route::post('payments/{payment}/reverse',     [PaymentController::class, 'reverse'])->name('payments.reverse');
    Route::get('payments/{payment}/receipt',      [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('payments/{payment}/receipt-url',  [PaymentController::class, 'receiptSignedUrl'])->name('payments.receipt-url');

    // Car Wash
    Route::get('car-wash/add-edit/{type}/{id?}', [CarWashController::class, 'addEdit'])->name('car-wash.addEdit');
    Route::get('car-wash/getData',                         [CarWashController::class, 'getData'])->name('car-wash.getData');
    Route::get('car-wash/services/getData',                [CarWashController::class, 'getServicesData'])->name('car-wash.services.getData');
    Route::get('car-wash/services/{carWashService}',       [CarWashController::class, 'showService'])->name('car-wash.services.show');
    Route::get('car-wash/services',                        [CarWashController::class, 'services'])->name('car-wash.services');
    Route::post('car-wash/services',                       [CarWashController::class, 'storeService'])->name('car-wash.services.store');
    Route::resource('car-wash', CarWashController::class);
    Route::put('car-wash/services/{carWashService}',       [CarWashController::class, 'updateService'])->name('car-wash.services.update');
    Route::get('car-wash/{carWash}/pdf',                   [CarWashController::class, 'pdf'])->name('car-wash.pdf');
    Route::get('car-wash/{carWash}/pdf-url',               [CarWashController::class, 'pdfSignedUrl'])->name('car-wash.pdf-url');

    // Reports
    Route::get('reports',                  [ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/revenue',          [ReportsController::class, 'revenue'])->name('reports.revenue');
    Route::get('reports/expenses',         [ReportsController::class, 'expenses'])->name('reports.expenses');
    Route::get('reports/trips',            [ReportsController::class, 'trips'])->name('reports.trips');
    Route::get('reports/invoices',         [ReportsController::class, 'invoices'])->name('reports.invoices');
    Route::get('reports/payments',         [ReportsController::class, 'payments'])->name('reports.payments');
    Route::get('reports/car-wash',         [ReportsController::class, 'carwash'])->name('reports.carwash');
    Route::get('reports/export/trips',     [ReportsController::class, 'exportTrips'])->name('reports.export.trips');
    Route::get('reports/export/expenses',  [ReportsController::class, 'exportExpenses'])->name('reports.export.expenses');
    Route::get('reports/export/invoices',  [ReportsController::class, 'exportInvoices'])->name('reports.export.invoices');

    // Activity Logs
    Route::get('activity-logs/getData', [ActivityLogController::class, 'getData'])->name('activity-logs.getData');
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    // Settings
    Route::get('settings',  [SettingsController::class, 'index'])->name('settings.index');
    Route::get('profile', [SettingsController::class, 'profile'])->name('profile.index');
    Route::get('settings/profile', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('settings',  [SettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/account', [SettingsController::class, 'updateAccount'])->name('settings.account.update');
    Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
});
