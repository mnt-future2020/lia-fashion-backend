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
        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['weight', 'location']); // 'weight' or 'location'

            // Weight-based fields (nullable for location type)
            $table->decimal('from_weight', 8, 2)->nullable();
            $table->decimal('to_weight', 8, 2)->nullable();
            $table->decimal('free_shipping_amount', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();

            // Location-based fields (nullable for weight type)
            $table->string('location')->nullable();
            $table->decimal('shipping_charge', 10, 2)->nullable();
            $table->string('estimated_days')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rules');
    }
};
