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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_name');
            $table->string('contact_person_name');
            $table->string('gst_number')->nullable();
            $table->string('email')->unique();
            $table->string('phone_number');
            $table->string('category')->nullable();
            $table->text('address_line1');
            $table->string('city');
            $table->string('district');
            $table->string('state');
            $table->string('country');
            $table->string('pincode');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
