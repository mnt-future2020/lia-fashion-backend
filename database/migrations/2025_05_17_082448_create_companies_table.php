<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('logo')->nullable();
            $table->string('store_name');
            $table->string('gst_no')->nullable();
            $table->string('contact_first_name');
            $table->string('contact_last_name');
            $table->string('mobile_no');
            $table->string('landline_no')->nullable();
            $table->string('email');
            $table->string('door_no');
            $table->string('street_name');
            $table->string('pin_code');
            $table->string('district');
            $table->string('state');
            $table->string('country');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
