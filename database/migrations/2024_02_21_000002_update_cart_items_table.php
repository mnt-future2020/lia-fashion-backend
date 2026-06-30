<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCartItemsTable extends Migration
{
    public function up()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_items', 'min_quantity_for_discount')) {
                $table->integer('min_quantity_for_discount')->nullable();
            }
            if (!Schema::hasColumn('cart_items', 'bulk_discount_amount')) {
                $table->decimal('bulk_discount_amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('cart_items', 'original_price')) {
                $table->decimal('original_price', 10, 2)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['min_quantity_for_discount', 'bulk_discount_amount', 'original_price']);
        });
    }
}
