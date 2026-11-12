<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A 3D customizer wrapper and its wrapping stock item are the same stock:
 * stock_product_id is that item (a wrapping material, counted in sheets), so
 * ERP Stock & replenishment, the vendor's Inventory and 3D Bouquet Management
 * all show and change one number.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bouquet_customization_options', 'stock_product_id')) {
            Schema::table('bouquet_customization_options', function (Blueprint $table) {
                $table->unsignedBigInteger('stock_product_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bouquet_customization_options', 'stock_product_id')) {
            Schema::table('bouquet_customization_options', function (Blueprint $table) {
                $table->dropColumn('stock_product_id');
            });
        }
    }
};
