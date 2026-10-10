<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * "Delete conversation" is per side: each participant can clear the chat
 * from their own list without removing it for the other person. Messages
 * up to the cleared time stay hidden from that side; a new message brings
 * the conversation back with only the newer messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('customer_cleared_at')->nullable();
            $table->timestamp('vendor_cleared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['customer_cleared_at', 'vendor_cleared_at']);
        });
    }
};
