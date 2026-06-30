<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('subcategory_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('sku_code')->unique();
            $table->text('description')->nullable();
            $table->boolean('has_multiple_options')->default(false);
            $table->json('sizes')->nullable();
            $table->float('tax_percentage')->nullable();
            $table->float('weight')->nullable();
            $table->string('weight_unit', 20)->default('gram');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
