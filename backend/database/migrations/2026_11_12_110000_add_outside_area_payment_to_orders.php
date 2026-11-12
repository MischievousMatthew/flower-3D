<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders outside the shop's delivery areas are paid after the shop approves:
 * GCash / Maya in full, or cash on delivery with a 50% down payment online.
 * The customer has until pay_by to pay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'deposit_amount')) {
                $table->decimal('deposit_amount', 12, 2)->nullable()->after('area_approved_at');
            }
            if (! Schema::hasColumn('orders', 'deposit_paid_at')) {
                $table->timestamp('deposit_paid_at')->nullable()->after('deposit_amount');
            }
            if (! Schema::hasColumn('orders', 'pay_by')) {
                $table->timestamp('pay_by')->nullable()->after('deposit_paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'deposit_paid_at', 'pay_by']);
        });
    }
};
