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
            $table->decimal('total_amount', 14, 2)->nullable()->after('per_trip_charge');
        });

        DB::table('quick_trip_executions')
            ->whereNotNull('per_trip_charge')
            ->update([
                'total_amount' => DB::raw('(pickup_count + drop_count) * per_trip_charge'),
            ]);
    }

    public function down(): void
    {
        Schema::table('quick_trip_executions', function (Blueprint $table) {
            $table->dropColumn('total_amount');
        });
    }
};
