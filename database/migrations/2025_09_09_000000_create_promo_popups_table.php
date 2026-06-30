<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promo_popups', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('image')->nullable();
            $table->json('theme')->nullable();
            $table->json('target_pages')->nullable();
            $table->enum('frequency', ['always','once','daily'])->default('once');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_popups');
    }
};


