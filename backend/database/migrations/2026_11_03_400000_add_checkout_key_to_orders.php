<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every checkout sends a key. The same key twice (a double click, a second
 * tab, a retried request) returns the order already made instead of a second
 * one; the database refuses a duplicate even when both arrive at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'checkout_key')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_key', 64)->nullable();
            $table->unique(['user_id', 'checkout_key']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'checkout_key']);
            $table->dropColumn('checkout_key');
        });
    }
};
