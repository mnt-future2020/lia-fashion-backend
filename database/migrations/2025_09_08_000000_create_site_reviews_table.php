<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->unsignedTinyInteger('rating');
            $table->text('text');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_reviews');
    }
};


