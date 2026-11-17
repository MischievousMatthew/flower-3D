<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * A supplier who can only supply part of an order now offers it: the order
 * waits ("supplier_offered") until the shop accepts the numbers, or declines
 * them and sends the order to another supplier. The supplier can't mark it
 * shipped before the shop accepts.
 */
return new class extends Migration
{
    private const STATUSES = ['draft', 'pending_approval', 'pending', 'supplier_offered', 'processing', 'shipped', 'partially_received', 'received', 'completed', 'cancelled'];

    public function up(): void
    {
        // The status column is an enum (a CHECK constraint on Postgres)
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn ($s) => "'{$s}'", self::STATUSES));
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check');
            DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status::text IN ({$list}))");
        }
    }

    public function down(): void
    {
        // Left as is: orders may already be waiting on an offer
    }
};
