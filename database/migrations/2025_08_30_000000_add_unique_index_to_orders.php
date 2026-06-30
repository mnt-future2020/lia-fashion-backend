<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueIndexToOrders extends Migration
{
    public function up()
    {
        // Try to drop existing indexes without checking
        try {
            DB::statement('DROP INDEX IF EXISTS orders_order_number_index ON orders');
            DB::statement('DROP INDEX IF EXISTS orders_order_number_unique ON orders');
        } catch (\Exception $e) {
            // Ignore any errors from dropping non-existent indexes
        }

        // Add unique index
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('order_number', 'orders_order_number_unique');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop unique index
            $table->dropUnique('orders_order_number_unique');
            // Add back normal index
            $table->index('order_number', 'orders_order_number_index');
        });
    }
}
