<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('trip_number')->unique();
            $table->date('trip_date');
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('trip_type')->default('Local')->comment('Local,Outstation,Airport Transfer,Corporate,Other');
            $table->string('pickup_location')->nullable();
            $table->string('drop_location')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('start_odometer')->nullable();
            $table->unsignedInteger('end_odometer')->nullable();
            $table->unsignedInteger('distance_km')->nullable();
            $table->string('driver_name')->nullable();
            $table->decimal('fare_amount', 12, 2)->default(0);
            $table->decimal('additional_charges', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('taxable_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_mode')->nullable()->comment('Cash,UPI,Bank Transfer,Card,Credit,Other');
            $table->decimal('amount_received', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->string('status')->default('completed')->comment('draft,completed,cancelled');
            $table->string('payment_status')->default('unpaid')->comment('unpaid,partial,paid');
            $table->boolean('is_invoiced')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['trip_date', 'status']);
            $table->index(['vehicle_id', 'trip_date']);
            $table->index(['customer_id', 'trip_date']);
            $table->index(['payment_status', 'is_invoiced']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
