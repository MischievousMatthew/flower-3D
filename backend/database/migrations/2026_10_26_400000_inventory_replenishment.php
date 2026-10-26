<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory & stock replenishment, on the existing product stock count:
 * what kind of stock each product is (flowers, wrapping, ribbons, vases…)
 * and the unit it's counted in. Materials the shop uses but doesn't sell are
 * products too, marked "internal" so customers never see them. Only adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            if (! Schema::hasColumn('products', 'stock_kind')) $t->string('stock_kind', 20)->nullable();
            if (! Schema::hasColumn('products', 'stock_unit')) $t->string('stock_unit', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            foreach (['stock_kind', 'stock_unit'] as $c) {
                if (Schema::hasColumn('products', $c)) $t->dropColumn($c);
            }
        });
    }
};
