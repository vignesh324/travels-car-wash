<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

foreach (['SCRIPT_NAME', 'PHP_SELF'] as $serverKey) {
    $scriptPath = $_SERVER[$serverKey] ?? '';

    if (str_ends_with($scriptPath, '/public/index.php')) {
        $_SERVER[$serverKey] = substr($scriptPath, 0, -strlen('/public/index.php')) . '/index.php';
    }
}

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
