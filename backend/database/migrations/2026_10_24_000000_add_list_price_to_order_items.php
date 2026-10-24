<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The regular price of each item when it was ordered. unit_price is what the
 * customer paid (a sale or promotion price when one was running), so the
 * difference is the discount the shop gave. Older orders leave it empty.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'list_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('list_price', 10, 2)->nullable()->after('unit_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'list_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('list_price');
            });
        }
    }
};
