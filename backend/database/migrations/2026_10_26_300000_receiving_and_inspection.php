<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receiving & quality inspection: each delivery against a purchase order is
 * recorded as a receipt, line by line (delivered, accepted, rejected and
 * missing, with the inspection), with the delivery receipt and photos.
 * Only accepted stems go into stock. Only adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $t) {
            // Turned away at inspection, and not coming at all (closed short)
            if (! Schema::hasColumn('purchase_order_items', 'rejected_qty')) $t->unsignedInteger('rejected_qty')->default(0);
            if (! Schema::hasColumn('purchase_order_items', 'cancelled_qty')) $t->unsignedInteger('cancelled_qty')->default(0);
        });

        if (! Schema::hasTable('purchase_order_receipts')) {
            Schema::create('purchase_order_receipts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('owner_id')->index();
                $t->unsignedBigInteger('purchase_order_id')->index();
                $t->string('receipt_number', 40);
                $t->string('delivery_ref', 80)->nullable();
                $t->date('received_date');
                $t->unsignedInteger('delivered_total')->default(0);
                $t->unsignedInteger('accepted_total')->default(0);
                $t->unsignedInteger('rejected_total')->default(0);
                $t->unsignedInteger('missing_total')->default(0);
                $t->text('notes')->nullable();
                $t->string('received_by_type', 20)->nullable();
                $t->unsignedBigInteger('received_by_id')->nullable();
                $t->string('received_by_name', 160)->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('purchase_order_receipt_lines')) {
            Schema::create('purchase_order_receipt_lines', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('receipt_id')->index();
                $t->unsignedBigInteger('purchase_order_item_id');
                $t->unsignedBigInteger('product_id')->nullable();
                $t->string('product_name');
                $t->unsignedInteger('expected_qty')->default(0);
                $t->unsignedInteger('delivered_qty')->default(0);
                $t->unsignedInteger('accepted_qty')->default(0);
                $t->unsignedInteger('rejected_qty')->default(0);
                $t->unsignedInteger('missing_qty')->default(0);
                // fresh, slightly_open, wilting, damaged
                $t->string('condition', 20)->nullable();
                $t->json('checks')->nullable();
                $t->string('reject_reason', 30)->nullable();
                // The missing stems won't come: close that part of the order
                $t->boolean('close_short')->default(false);
                $t->unsignedBigInteger('warehouse_batch_id')->nullable();
                $t->string('notes', 500)->nullable();
            });
        }

        if (! Schema::hasTable('purchase_order_receipt_files')) {
            Schema::create('purchase_order_receipt_files', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('owner_id')->index();
                $t->unsignedBigInteger('receipt_id')->index();
                // receipt (the delivery receipt or invoice) or photo
                $t->string('kind', 10);
                $t->string('url', 500);
                $t->string('public_id', 255)->nullable();
                $t->string('mime', 80)->nullable();
                $t->string('original_name', 200)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipt_files');
        Schema::dropIfExists('purchase_order_receipt_lines');
        Schema::dropIfExists('purchase_order_receipts');
        Schema::table('purchase_order_items', function (Blueprint $t) {
            foreach (['rejected_qty', 'cancelled_qty'] as $c) {
                if (Schema::hasColumn('purchase_order_items', $c)) $t->dropColumn($c);
            }
        });
    }
};
