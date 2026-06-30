<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // First make nullable, then modify default
            $table->decimal('tax_percentage', 5, 2)->nullable()->default(0)->change();
            $table->decimal('tax_amount', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->decimal('tax_percentage', 5, 2)->nullable(false)->change();
            $table->decimal('tax_amount', 10, 2)->change();
        });
    }
};
