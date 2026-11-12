<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materials bought in packs: stock is always counted in the smallest unit
 * (stock_unit, e.g. "sheet"); a pack (pack_unit, e.g. "roll") holds pack_size
 * of them, so 2 rolls of 50 sheets add 100 sheets.
 *
 * The 3D customizer's wrappers, ribbons and fillers can use one of those
 * materials: stock_product_id, stock_per_use pieces per bouquet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'pack_unit')) {
                $table->string('pack_unit', 20)->nullable();
            }
            if (! Schema::hasColumn('products', 'pack_size')) {
                $table->unsignedInteger('pack_size')->nullable();
            }
        });
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            if (! Schema::hasColumn('bouquet_customization_options', 'stock_product_id')) {
                $table->unsignedBigInteger('stock_product_id')->nullable();
            }
            if (! Schema::hasColumn('bouquet_customization_options', 'stock_per_use')) {
                $table->unsignedInteger('stock_per_use')->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['pack_unit', 'pack_size'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            foreach (['stock_product_id', 'stock_per_use'] as $column) {
                if (Schema::hasColumn('bouquet_customization_options', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
