<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('source')->default('travels')->comment('travels,carwash,mixed');

            // Customer snapshot at time of issue
            $table->string('customer_name');
            $table->string('customer_gstin', 20)->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_state')->nullable();
            $table->string('customer_state_code', 5)->nullable();

            // Business snapshot at time of issue
            $table->string('business_name')->nullable();
            $table->string('business_gstin', 20)->nullable();
            $table->text('business_address')->nullable();
            $table->json('bank_details_snapshot')->nullable();

            $table->string('place_of_supply')->nullable();

            // Financials
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('taxable_amount', 12, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);

            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->text('footer')->nullable();

            $table->string('status')->default('draft')->comment('draft,issued,partial,paid,cancelled');
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['invoice_date', 'status']);
            $table->index('status');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->string('source')->default('travels')->comment('travels,carwash');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('taxable_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_trip_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('trips')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['invoice_id', 'trip_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_trip_links');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
