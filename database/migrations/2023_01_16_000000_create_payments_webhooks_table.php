<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_webhooks', function (Blueprint $table) {
            $table->id();
            $table->text('body')->nullable();
            $table->text('headers')->nullable();
            $table->string('provider')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments_webhooks');
    }
};
