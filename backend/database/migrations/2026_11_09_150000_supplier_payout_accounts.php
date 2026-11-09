<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a supplier wants to be paid (bank or e-wallet account), given when
 * they confirm an order from its email link. Kept on the order (what Finance
 * pays into) and on the supplier (filled in next time). The account number
 * is stored encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['suppliers', 'purchase_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'payout_method')) {
                    $table->string('payout_method', 20)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'payout_provider')) {
                    $table->string('payout_provider', 60)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'payout_account_name')) {
                    $table->string('payout_account_name', 120)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'payout_account_number')) {
                    // Encrypted, so longer than the number itself
                    $table->text('payout_account_number')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['suppliers', 'purchase_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['payout_method', 'payout_provider', 'payout_account_name', 'payout_account_number'] as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
