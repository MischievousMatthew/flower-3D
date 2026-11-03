<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers answer a purchase order from a private link in its email
 * (confirm, decline, mark shipped), like vendors resubmitting documents.
 * Only the link's hash is stored; links expire and a resend replaces them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_order_supplier_links')) {
            Schema::create('purchase_order_supplier_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id')->index();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->string('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_opened_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'supplier_confirmed_at')) {
                $table->timestamp('supplier_confirmed_at')->nullable();
            }
            if (! Schema::hasColumn('purchase_orders', 'supplier_shipped_at')) {
                $table->timestamp('supplier_shipped_at')->nullable();
            }
            if (! Schema::hasColumn('purchase_orders', 'supplier_declined_at')) {
                $table->timestamp('supplier_declined_at')->nullable();
            }
            if (! Schema::hasColumn('purchase_orders', 'supplier_note')) {
                $table->text('supplier_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_supplier_links');
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['supplier_confirmed_at', 'supplier_shipped_at', 'supplier_declined_at', 'supplier_note']);
        });
    }
};
