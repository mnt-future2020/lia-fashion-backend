<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBulkDiscountToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_quantity_for_discount')->nullable()->after('tax_percentage');
            $table->decimal('discounted_price', 10, 2)->nullable()->after('min_quantity_for_discount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('min_quantity_for_discount');
            $table->dropColumn('discounted_price');
        });
    }
}
