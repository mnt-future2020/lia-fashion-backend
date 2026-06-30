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
        Schema::table('pos_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_order_items', 'size')) {
                $table->string('size')->nullable()->after('price');
            }
            if (!Schema::hasColumn('pos_order_items', 'color')) {
                $table->string('color')->nullable()->after('size');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table) {
            $table->dropColumn(['size', 'color']);
        });
    }
};
