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
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10)->default('CXI'); // Prefix for invoice numbers (max 10 chars)
            $table->date('financial_year_start')->nullable(); // Start of financial year
            $table->date('financial_year_end')->nullable(); // End of financial year
            $table->string('last_invoice_number', 20)->nullable(); // Last generated invoice number
            $table->integer('last_sequence_number')->default(0); // Last used sequence number
            $table->boolean('is_active')->default(true); // Active setting flag
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_settings');
    }
};
