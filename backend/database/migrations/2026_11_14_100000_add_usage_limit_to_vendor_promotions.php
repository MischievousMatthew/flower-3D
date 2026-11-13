<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many people can use a promotion: max_uses (null = no limit) and
 * uses_count, the spots taken. A spot is claimed in the same transaction as the
 * order (an atomic "uses_count + 1 while below max_uses"), so two customers
 * can't take the last one, and given back when the order is cancelled. Each
 * customer uses a promotion once: one promotion_redemptions row per
 * (promotion, user), which also says which order holds the spot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_promotions', 'max_uses')) {
                $table->unsignedInteger('max_uses')->nullable();
            }
            if (! Schema::hasColumn('vendor_promotions', 'uses_count')) {
                $table->unsignedInteger('uses_count')->default(0);
            }
        });

        // Postgres has no unsigned: the database itself refuses a count below 0
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE vendor_promotions DROP CONSTRAINT IF EXISTS vendor_promotions_uses_count_check');
            DB::statement('ALTER TABLE vendor_promotions ADD CONSTRAINT vendor_promotions_uses_count_check CHECK (uses_count >= 0)');
        }

        if (! Schema::hasTable('promotion_redemptions')) {
            Schema::create('promotion_redemptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('promotion_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('order_id');
                $table->timestamps();
                $table->unique(['promotion_id', 'user_id']);
                $table->index('order_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE vendor_promotions DROP CONSTRAINT IF EXISTS vendor_promotions_uses_count_check');
        }
        Schema::table('vendor_promotions', function (Blueprint $table) {
            foreach (['max_uses', 'uses_count'] as $column) {
                if (Schema::hasColumn('vendor_promotions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
