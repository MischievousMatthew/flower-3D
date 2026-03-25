<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Column may already exist if a previous migration attempt partially ran
        if (!Schema::hasColumn('purchase_order_items', 'product_id')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->after('id');
                $table->foreign('product_id')
                      ->references('id')
                      ->on('products')
                      ->nullOnDelete();
            });
        }

        // Backfill existing rows: match product by name
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'sqlite') {
            DB::statement("
                UPDATE purchase_order_items poi
                INNER JOIN products p
                    ON  p.product_name = poi.product_name
                    AND p.deleted_at   IS NULL
                SET poi.product_id = p.id
                WHERE poi.product_id IS NULL
            ");
        } elseif ($driver === 'pgsql') {
            DB::statement("
                UPDATE purchase_order_items poi
                SET product_id = p.id
                FROM products p
                WHERE poi.product_name = p.product_name
                  AND p.deleted_at IS NULL
                  AND poi.product_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};