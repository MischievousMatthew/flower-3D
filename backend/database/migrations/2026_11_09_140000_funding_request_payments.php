<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approving a funding request now sets a budget; the money goes out later,
 * when the supplier's payment terms say so: once they confirm (pay before
 * delivery) or once the stock is in (pay on or after delivery). Each payment,
 * and any refund from a supplier who delivered less than was paid for, is a
 * row in funding_request_payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funding_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('funding_requests', 'payment_timing')) {
                // before_delivery | after_delivery, copied from the supplier when the order is made
                $table->string('payment_timing', 20)->nullable();
            }
            if (! Schema::hasColumn('funding_requests', 'supplier_terms')) {
                $table->string('supplier_terms', 60)->nullable();
            }
            if (! Schema::hasColumn('funding_requests', 'payment_due_days')) {
                $table->unsignedSmallInteger('payment_due_days')->nullable();
            }
            if (! Schema::hasColumn('funding_requests', 'supplier_credit_settled_at')) {
                $table->timestamp('supplier_credit_settled_at')->nullable();
            }
        });

        if (! Schema::hasTable('funding_request_payments')) {
            Schema::create('funding_request_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id')->index();
                $table->foreignId('funding_request_id')->constrained('funding_requests')->cascadeOnDelete();
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
                // payment: money sent to the supplier
                // refund: money the supplier sent back
                // credit: what the supplier owes, kept as credit for a later order
                $table->string('kind', 20);
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 20)->nullable();
                $table->string('payment_channel', 60)->nullable();
                $table->string('reference', 100)->nullable();
                $table->date('paid_on');
                $table->string('note', 500)->nullable();
                $table->string('recorded_by_name')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_request_payments');
        Schema::table('funding_requests', function (Blueprint $table) {
            foreach (['payment_timing', 'supplier_terms', 'payment_due_days', 'supplier_credit_settled_at'] as $col) {
                if (Schema::hasColumn('funding_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
