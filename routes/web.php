<?php

use App\Http\Controllers\Api\CarWashController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Public signed PDF routes (no auth middleware — signature validates access)
Route::get('/pdf/invoice/{invoice}', [InvoiceController::class, 'pdfSigned'])
    ->name('pdf.invoice')->middleware('signed');
Route::get('/pdf/receipt/{payment}', [PaymentController::class, 'receiptSigned'])
    ->name('pdf.receipt')->middleware('signed');
Route::get('/pdf/carwash/{carWashTransaction}', [CarWashController::class, 'pdfSigned'])
    ->name('pdf.carwash')->middleware('signed');

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

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');


