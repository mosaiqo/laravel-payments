<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mosaiqo\LaravelPayments\LaravelPayments;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->string('type');
            $table->string('provider_id')->unique();
            $table->string('provider');
            $table->string('customer_id');
            $table->foreignIdFor(LaravelPayments::resolveCustomerModel(), 'payments_customer_id')->constrained()->nullable();
            $table->string('status');
            $table->string('price')->nullable();
            $table->string('product_id');
            $table->string('variant_id');
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->string('pause_mode')->nullable();
            $table->timestamp('pause_resumes_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments_subscriptions');
    }
};
