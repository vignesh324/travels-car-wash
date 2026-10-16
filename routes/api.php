<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessExpenseController;
use App\Http\Controllers\Api\BusinessProfileController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\CarWashController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleExpenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::put('change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('dashboard/charts', [DashboardController::class, 'charts']);
    Route::get('dashboard/recent', [DashboardController::class, 'recentActivity']);

    Route::get('settings/business', [BusinessProfileController::class, 'show']);
    Route::post('settings/business', [BusinessProfileController::class, 'update']);

    Route::get('vehicles', [VehicleController::class, 'index']);
    Route::post('vehicles', [VehicleController::class, 'store']);
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'show']);
    Route::put('vehicles/{vehicle}', [VehicleController::class, 'update']);
    Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy']);
    Route::post('vehicles/{uuid}/restore', [VehicleController::class, 'restore']);

    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers/{customer}', [CustomerController::class, 'show']);
    Route::put('customers/{customer}', [CustomerController::class, 'update']);
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
    Route::post('customers/{uuid}/restore', [CustomerController::class, 'restore']);
    Route::get('customers/{customer}/statement', [CustomerController::class, 'statement']);

    Route::get('trips', [TripController::class, 'index']);
    Route::post('trips', [TripController::class, 'store']);
    Route::get('trips/summary', [TripController::class, 'summary']);
    Route::get('trips/{trip}', [TripController::class, 'show']);
    Route::put('trips/{trip}', [TripController::class, 'update']);
    Route::delete('trips/{trip}', [TripController::class, 'destroy']);

    Route::get('expenses/vehicle', [VehicleExpenseController::class, 'index']);
    Route::post('expenses/vehicle', [VehicleExpenseController::class, 'store']);
    Route::get('expenses/vehicle/{vehicleExpense}', [VehicleExpenseController::class, 'show']);
    Route::put('expenses/vehicle/{vehicleExpense}', [VehicleExpenseController::class, 'update']);
    Route::delete('expenses/vehicle/{vehicleExpense}', [VehicleExpenseController::class, 'destroy']);

    Route::get('expenses/business', [BusinessExpenseController::class, 'index']);
    Route::post('expenses/business', [BusinessExpenseController::class, 'store']);
    Route::get('expenses/business/{businessExpense}', [BusinessExpenseController::class, 'show']);
    Route::put('expenses/business/{businessExpense}', [BusinessExpenseController::class, 'update']);
    Route::delete('expenses/business/{businessExpense}', [BusinessExpenseController::class, 'destroy']);

    Route::get('expense-categories', [ExpenseCategoryController::class, 'index']);
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store']);
    Route::put('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update']);

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::get('invoices/{invoice}/pdf-url', [InvoiceController::class, 'pdfSignedUrl']);

    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'store']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::post('payments/{payment}/allocate', [PaymentController::class, 'allocate']);
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse']);
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt']);
    Route::get('payments/{payment}/receipt-url', [PaymentController::class, 'receiptSignedUrl']);

    Route::get('car-wash/services', [CarWashController::class, 'services']);
    Route::post('car-wash/services', [CarWashController::class, 'storeService']);
    Route::put('car-wash/services/{carWashService}', [CarWashController::class, 'updateService']);
    Route::get('car-wash/transactions', [CarWashController::class, 'index']);
    Route::post('car-wash/transactions', [CarWashController::class, 'store']);
    Route::get('car-wash/transactions/{carWashTransaction}', [CarWashController::class, 'show']);
    Route::put('car-wash/transactions/{carWashTransaction}', [CarWashController::class, 'update']);
    Route::delete('car-wash/transactions/{carWashTransaction}', [CarWashController::class, 'destroy']);
    Route::get('car-wash/transactions/{carWashTransaction}/pdf', [CarWashController::class, 'pdf']);
    Route::get('car-wash/transactions/{carWashTransaction}/pdf-url', [CarWashController::class, 'pdfSignedUrl']);

    Route::get('reports/revenue', [ReportsController::class, 'revenue']);
    Route::get('reports/expenses', [ReportsController::class, 'expenses']);
    Route::get('reports/trips', [ReportsController::class, 'trips']);
    Route::get('reports/invoices', [ReportsController::class, 'invoices']);
    Route::get('reports/payments', [ReportsController::class, 'payments']);
    Route::get('reports/car-wash', [ReportsController::class, 'carwash']);
    Route::get('reports/export/trips', [ReportsController::class, 'exportTrips']);
    Route::get('reports/export/expenses', [ReportsController::class, 'exportExpenses']);
    Route::get('reports/export/invoices', [ReportsController::class, 'exportInvoices']);

    Route::get('activity-logs', [ActivityLogController::class, 'index']);
});
