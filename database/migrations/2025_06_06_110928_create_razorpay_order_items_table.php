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
        Schema::create('razorpay_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_transaction_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->json('metadata')->nullable(); // For storing additional info like tax
            $table->timestamps();

            $table->foreign('payment_transaction_id')
                ->references('id')
                ->on('payment_transactions')
                ->onDelete('cascade');
            
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('razorpay_order_items');
    }
};
