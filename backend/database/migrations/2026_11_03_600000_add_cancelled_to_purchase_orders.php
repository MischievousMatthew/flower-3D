<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders can be cancelled (with a reason) while nothing has arrived.
 */
return new class extends Migration
{
    private const STATUSES = ['draft', 'pending_approval', 'pending', 'processing', 'shipped', 'partially_received', 'received', 'completed', 'cancelled'];

    public function up(): void
    {
        // The status column is an enum (a CHECK constraint on Postgres): allow "cancelled"
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn ($s) => "'{$s}'", self::STATUSES));
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check');
            DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status::text IN ({$list}))");
        }

        Schema::table('purchase_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                $t->timestamp('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('purchase_orders', 'cancel_reason')) {
                $t->text('cancel_reason')->nullable();
            }
            if (! Schema::hasColumn('purchase_orders', 'cancelled_by_name')) {
                $t->string('cancelled_by_name', 160)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->dropColumn(['cancelled_at', 'cancel_reason', 'cancelled_by_name']);
        });
    }
};
