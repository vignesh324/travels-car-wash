<?php

namespace App\Http\Controllers;

use App\Services\DatabaseSqlExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseExportController extends Controller
{
    public function __invoke(DatabaseSqlExporter $exporter): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($exporter): void {
                foreach ($exporter->lines() as $line) {
                    echo $line;
                }
            },
            'travels-car-wash-'.now()->format('Y-m-d-His').'.sql',
            [
                'Content-Type' => 'application/sql; charset=UTF-8',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
