<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase order management: drafts and approval before an order goes to the
 * supplier, order and expected delivery dates, receiving part of an order,
 * and a history of everything that happened to it.
 *
 * Only adds. The existing statuses (pending, processing, shipped, received,
 * completed) and their flow through shipments and scanning stay as they are.
 */
return new class extends Migration
{
    private const STATUSES = ['draft', 'pending_approval', 'pending', 'processing', 'shipped', 'partially_received', 'received', 'completed'];

    public function up(): void
    {
        // The status column is an enum (a CHECK constraint on Postgres): allow the new values
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn ($s) => "'{$s}'", self::STATUSES));
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check');
            DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status::text IN ({$list}))");
        }

        Schema::table('purchase_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('purchase_orders', 'order_date')) $t->date('order_date')->nullable();
            if (! Schema::hasColumn('purchase_orders', 'expected_date')) $t->date('expected_date')->nullable();
            if (! Schema::hasColumn('purchase_orders', 'notes')) $t->text('notes')->nullable();
            if (! Schema::hasColumn('purchase_orders', 'created_by_name')) $t->string('created_by_name', 160)->nullable();
            if (! Schema::hasColumn('purchase_orders', 'submitted_at')) $t->timestamp('submitted_at')->nullable();
            if (! Schema::hasColumn('purchase_orders', 'approved_at')) $t->timestamp('approved_at')->nullable();
            if (! Schema::hasColumn('purchase_orders', 'approved_by_name')) $t->string('approved_by_name', 160)->nullable();
            if (! Schema::hasColumn('purchase_orders', 'sent_at')) $t->timestamp('sent_at')->nullable();
            if (! Schema::hasColumn('purchase_orders', 'sent_to_email')) $t->string('sent_to_email')->nullable();
            if (! Schema::hasColumn('purchase_orders', 'completed_at')) $t->timestamp('completed_at')->nullable();
        });

        Schema::table('purchase_order_items', function (Blueprint $t) {
            if (! Schema::hasColumn('purchase_order_items', 'received_qty')) $t->unsignedInteger('received_qty')->default(0);
            if (! Schema::hasColumn('purchase_order_items', 'unit')) $t->string('unit', 20)->nullable();
            if (! Schema::hasColumn('purchase_order_items', 'supplier_product_id')) $t->unsignedBigInteger('supplier_product_id')->nullable();
        });

        if (! Schema::hasTable('purchase_order_events')) {
            Schema::create('purchase_order_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('owner_id')->index();
                $t->unsignedBigInteger('purchase_order_id')->index();
                $t->string('event', 30);
                $t->string('from_status', 30)->nullable();
                $t->string('to_status', 30)->nullable();
                $t->text('note')->nullable();
                $t->string('actor_type', 20)->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('actor_name', 160)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        // Orders placed before this had no order date: their creation day is it
        if (Schema::hasColumn('purchase_orders', 'order_date')) {
            DB::table('purchase_orders')->whereNull('order_date')->whereNotNull('created_at')->update(['order_date' => DB::raw('DATE(created_at)')]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_events');
        Schema::table('purchase_order_items', function (Blueprint $t) {
            foreach (['received_qty', 'unit', 'supplier_product_id'] as $c) {
                if (Schema::hasColumn('purchase_order_items', $c)) $t->dropColumn($c);
            }
        });
        Schema::table('purchase_orders', function (Blueprint $t) {
            foreach (['order_date', 'expected_date', 'notes', 'created_by_name', 'submitted_at', 'approved_at', 'approved_by_name', 'sent_at', 'sent_to_email', 'completed_at'] as $c) {
                if (Schema::hasColumn('purchase_orders', $c)) $t->dropColumn($c);
            }
        });
        // The status constraint is left wide: orders may already use the new statuses
    }
};
