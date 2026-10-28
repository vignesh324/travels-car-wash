<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_trip_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('name');
            $table->string('pickup_location');
            $table->string('drop_location');
            $table->string('route')->nullable();
            $table->decimal('per_trip_charge', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['customer_id', 'is_active', 'deleted_at']);
        });

        Schema::create('quick_trip_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('quick_trip_configuration_id')
                ->constrained('quick_trip_configurations')
                ->restrictOnDelete();
            $table->date('trip_date');
            $table->unsignedInteger('pickup_count')->default(0);
            $table->unsignedInteger('drop_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['quick_trip_configuration_id', 'trip_date'],
                'quick_trip_config_date_unique'
            );
            $table->index(['customer_id', 'trip_date']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('quick_trip_execution_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('quick_trip_executions')
                ->restrictOnDelete();
            $table->string('quick_trip_direction')->nullable()->after('quick_trip_execution_id');
            $table->unsignedInteger('quick_trip_sequence')->nullable()->after('quick_trip_direction');
            $table->unique(
                ['quick_trip_execution_id', 'quick_trip_direction', 'quick_trip_sequence'],
                'trips_quick_execution_direction_sequence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropUnique('trips_quick_execution_direction_sequence_unique');
            $table->dropConstrainedForeignId('quick_trip_execution_id');
            $table->dropColumn(['quick_trip_direction', 'quick_trip_sequence']);
        });

        Schema::dropIfExists('quick_trip_executions');
        Schema::dropIfExists('quick_trip_configurations');
    }
};
