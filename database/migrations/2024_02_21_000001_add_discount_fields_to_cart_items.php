<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountFieldsToCartItems extends Migration
{
    public function up()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->integer('min_quantity_for_discount')->nullable();
            $table->decimal('bulk_discount_amount', 10, 2)->nullable();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->decimal('bulk_discount_total', 10, 2)->default(0);
        });
    }

    public function down()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['min_quantity_for_discount', 'bulk_discount_amount']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('bulk_discount_total');
        });
    }
}
