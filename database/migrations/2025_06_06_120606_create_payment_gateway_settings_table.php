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
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_name');  // e.g., 'razorpay', 'stripe', etc.
            $table->string('key_id');
            $table->string('key_secret');
            $table->boolean('is_sandbox')->default(false);
            $table->boolean('is_active')->default(false);
            $table->text('webhook_secret')->nullable();
            $table->text('additional_settings')->nullable(); // JSON field for any additional settings
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
