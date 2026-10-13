<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('expense_date');
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->decimal('amount_before_tax', 12, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_mode')->default('Cash')->comment('Cash,UPI,Bank Transfer,Card,Credit,Other');
            $table->string('payment_status')->default('paid')->comment('paid,unpaid,partial');
            $table->date('payment_date')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['expense_date', 'vehicle_id']);
            $table->index(['category_id', 'expense_date']);
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_expenses');
    }
};
