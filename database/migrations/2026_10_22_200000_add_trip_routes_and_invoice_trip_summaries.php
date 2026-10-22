<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('route')->nullable()->after('drop_location');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('vehicle_type')->nullable()->after('source');
            $table->string('route')->nullable()->after('vehicle_type');
            $table->unsignedInteger('service_days')->nullable()->after('route');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type', 'route', 'service_days']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('route');
        });
    }
};
