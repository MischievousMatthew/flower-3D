<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * "Archive conversation" is per side, like delete: it moves the chat to that
 * participant's Archived tab without touching the other person's inbox. A new
 * message after the archive time brings it back to the inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('customer_archived_at')->nullable();
            $table->timestamp('vendor_archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['customer_archived_at', 'vendor_archived_at']);
        });
    }
};
