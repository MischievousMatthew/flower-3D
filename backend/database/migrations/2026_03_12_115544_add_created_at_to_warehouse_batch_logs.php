<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_batch_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouse_batch_logs', 'created_at')) {
                $table->timestamp('created_at')->nullable()->useCurrent();
            }
            // Drop updated_at if it somehow exists to avoid confusion
            if (Schema::hasColumn('warehouse_batch_logs', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_batch_logs', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_batch_logs', 'created_at')) {
                $table->dropColumn('created_at');
            }
        });
    }
};