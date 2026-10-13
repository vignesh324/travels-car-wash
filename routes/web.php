<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

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


