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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_customer_id')->nullable()->constrained()->onDelete('set null');
            $table->string('invoice_number');
            $table->string('order_number');
            $table->enum('transaction_type', ['POS', 'Online'])->default('POS');
            $table->string('payment_method');
            $table->enum('payment_status', ['Paid', 'Pending', 'Failed'])->default('Paid');
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->timestamp('transaction_date');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Index for faster filtering and searching
            $table->index('invoice_number');
            $table->index('order_number');
            $table->index('transaction_type');
            $table->index('payment_status');
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
