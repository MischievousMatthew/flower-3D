<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an order took off the shelves: [[product_id, colour|null, quantity], …].
 * A bouquet whose flowers are linked to single-flower products takes those
 * flowers, not itself, so this is kept to put back exactly the same stock if
 * the order is cancelled, even after the bouquet's flowers change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'stock_lines')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->json('stock_lines')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'stock_lines')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('stock_lines');
            });
        }
    }
};
