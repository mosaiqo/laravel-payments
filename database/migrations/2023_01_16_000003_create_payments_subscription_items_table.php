<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments_subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payments_subscription_id');
            $table->string('provider');
            $table->string('provider_id')->unique();
            $table->string('subscription_id');
            $table->string('product_id')->unique();
            $table->string('price_id');
            $table->boolean('is_usage_based')->default(false);
            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_id', 'payments_subscription_id', 'price_id'], 'subscription_item_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments_subscription_items');
    }
};
