<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_locations', 'warehouse_id')) {
                // Nullable so existing rows don't break
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('id');
                $table->foreign('warehouse_id')
                      ->references('id')
                      ->on('warehouses')
                      ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_locations', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_locations', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            }
        });
    }
};