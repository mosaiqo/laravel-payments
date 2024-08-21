<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billable_id')->nullable();
            $table->string('billable_type')->nullable();
            $table->string('provider');
            $table->string('provider_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id', 'billable_id', 'billable_type'], 'unique_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments_customers');
    }
};
