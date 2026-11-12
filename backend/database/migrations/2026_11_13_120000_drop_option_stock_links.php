<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Only bouquets link products for their stock; the 3D customizer's wrappers,
 * ribbons and fillers don't. Removes the link columns added just before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            foreach (['stock_product_id', 'stock_per_use'] as $column) {
                if (Schema::hasColumn('bouquet_customization_options', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            if (! Schema::hasColumn('bouquet_customization_options', 'stock_product_id')) {
                $table->unsignedBigInteger('stock_product_id')->nullable();
            }
            if (! Schema::hasColumn('bouquet_customization_options', 'stock_per_use')) {
                $table->unsignedInteger('stock_per_use')->default(1);
            }
        });
    }
};
