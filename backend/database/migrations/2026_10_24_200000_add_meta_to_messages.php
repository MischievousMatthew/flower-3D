<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured details for chat messages that are more than text: an order
 * update or a shared product, shown as a card in both the shop's and the
 * customer's chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'meta')) {
            Schema::table('messages', fn (Blueprint $table) => $table->json('meta')->nullable()->after('attachments'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('messages', 'meta')) {
            Schema::table('messages', fn (Blueprint $table) => $table->dropColumn('meta'));
        }
    }
};
