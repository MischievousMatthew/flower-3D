<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders to an address outside the shop's delivery areas wait for the shop
 * to approve delivering them (or to cancel) before they go any further.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'outside_area')) {
                $table->boolean('outside_area')->default(false)->after('delivery_address');
            }
            if (! Schema::hasColumn('orders', 'area_approved_at')) {
                $table->timestamp('area_approved_at')->nullable()->after('outside_area');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['outside_area', 'area_approved_at']);
        });
    }
};
