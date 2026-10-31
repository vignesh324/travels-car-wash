<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_trip_executions', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('customer_id')->constrained('vehicles')->nullOnDelete();
            $table->string('driver_name')->nullable()->after('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::table('quick_trip_executions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn('driver_name');
        });
    }
};
