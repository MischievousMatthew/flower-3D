<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'preparation_days')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('preparation_days')->default(0)->after('supplier_sku');
            });
        }

        DB::table('products')->update([
            'preparation_days' => DB::raw('COALESCE(preparation_days, supplier_lead_time, 0)'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'preparation_days')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('preparation_days');
            });
        }
    }
};
