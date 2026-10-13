<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

Route::get('/migrate', function () {
    Artisan::call('migrate:fresh --seed');
    return response()->json(['message' => 'Database migrated and seeded']);
});

Route::get('/cache-clear', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    return response()->json(['message' => 'Cache cleared']);
});
