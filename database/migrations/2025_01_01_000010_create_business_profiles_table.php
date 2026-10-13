<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('logo')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('state')->nullable();
            $table->string('state_code', 5)->nullable();
            $table->string('currency', 5)->default('INR');
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->string('date_format', 20)->default('DD-MM-YYYY');
            $table->json('bank_details')->nullable();
            $table->string('upi_id')->nullable();
            $table->string('invoice_prefix', 20)->default('INV');
            $table->string('receipt_prefix', 20)->default('RCP');
            $table->string('financial_year_start', 5)->default('04-01');
            $table->unsignedTinyInteger('invoice_number_reset')->default(1)->comment('1=yearly,0=never');
            $table->unsignedInteger('invoice_number_next')->default(1);
            $table->string('default_payment_terms')->nullable();
            $table->text('invoice_footer')->nullable();
            $table->json('tax_config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
