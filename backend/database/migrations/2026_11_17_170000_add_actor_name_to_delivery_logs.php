<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who moved a delivery to a step, when it wasn't a staff scan: the shop owner
 * on the Orders page, or the customer confirming they got it. scanned_by only
 * points to employees, so these showed as "System".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_logs') && ! Schema::hasColumn('delivery_logs', 'actor_name')) {
            Schema::table('delivery_logs', function (Blueprint $table) {
                $table->string('actor_name', 120)->nullable()->after('scanned_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_logs', 'actor_name')) {
            Schema::table('delivery_logs', fn (Blueprint $table) => $table->dropColumn('actor_name'));
        }
    }
};
