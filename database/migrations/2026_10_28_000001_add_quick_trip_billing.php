<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_trip_executions', function (Blueprint $table) {
            $table->decimal('per_trip_charge', 12, 2)->nullable()->after('drop_count');
        });

        DB::table('quick_trip_executions')
            ->whereNull('per_trip_charge')
            ->orderBy('id')
            ->chunkById(100, function ($executions) {
                foreach ($executions as $execution) {
                    $charge = DB::table('trips')
                        ->where('quick_trip_execution_id', $execution->id)
                        ->orderBy('id')
                        ->value('fare_amount');
                    $charge ??= DB::table('quick_trip_configurations')
                        ->where('id', $execution->quick_trip_configuration_id)
                        ->value('per_trip_charge');

                    if ($charge !== null) {
                        DB::table('quick_trip_executions')
                            ->where('id', $execution->id)
                            ->update(['per_trip_charge' => $charge]);
                    }
                }
            });

        Schema::create('invoice_quick_trip_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('quick_trip_execution_id')
                ->constrained('quick_trip_executions')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['invoice_id', 'quick_trip_execution_id'],
                'invoice_quick_trip_link_unique'
            );
            $table->index('quick_trip_execution_id', 'invoice_quick_trip_execution_idx');
        });

        DB::table('quick_trip_executions')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($executions) {
                foreach ($executions as $execution) {
                    $invoiceIds = DB::table('invoice_trip_links')
                        ->join('trips', 'trips.id', '=', 'invoice_trip_links.trip_id')
                        ->where('trips.quick_trip_execution_id', $execution->id)
                        ->distinct()
                        ->pluck('invoice_trip_links.invoice_id');

                    foreach ($invoiceIds as $invoiceId) {
                        DB::table('invoice_quick_trip_links')->insertOrIgnore([
                            'invoice_id' => $invoiceId,
                            'quick_trip_execution_id' => $execution->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_quick_trip_links');
        Schema::table('quick_trip_executions', function (Blueprint $table) {
            $table->dropColumn('per_trip_charge');
        });
    }
};
