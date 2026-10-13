<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('registration_number', 20)->unique();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('type')->nullable()->comment('Car,Van,Bus,Tempo,Other');
            $table->string('manufacturer')->nullable();
            $table->string('fuel_type')->default('Diesel')->comment('Petrol,Diesel,CNG,Electric,Other');
            $table->unsignedSmallInteger('seating_capacity')->nullable();
            $table->string('ownership_type')->default('Owned')->comment('Owned,Leased,Attached');
            $table->date('purchase_date')->nullable();
            $table->string('default_driver_name')->nullable();
            $table->string('default_driver_phone', 15)->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('permit_expiry')->nullable();
            $table->date('fitness_expiry')->nullable();
            $table->date('puc_expiry')->nullable();
            $table->unsignedInteger('current_odometer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'deleted_at']);
            $table->index('type');
            $table->index('fuel_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
