<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mosaiqo\LaravelPayments\LaravelPayments;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_orders', function (Blueprint $table) {
            $table->id();
            $table->string('provider_id');
            $table->string('provider');
            $table->string('customer_id');
            $table->foreignIdFor(LaravelPayments::resolveCustomerModel(), 'payments_customer_id')->nullable();
            $table->uuid('identifier')->unique();
            $table->string('product_id')->index();
            $table->string('variant_id')->index();
            $table->integer('order_number')->unique();
            $table->string('currency');
            $table->integer('subtotal');
            $table->integer('discount_total');
            $table->integer('tax');
            $table->integer('total');
            $table->string('tax_name')->nullable();
            $table->string('status');
            $table->string('receipt_url')->nullable();
            $table->boolean('refunded');
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('ordered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments_orders');
    }
};
