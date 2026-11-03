<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the person who asked last opened their request, so a reply after
 * that shows as unread (red dot on their name and on "Help & support").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'requester_seen_at')) {
                $table->timestamp('requester_seen_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', fn (Blueprint $t) => $t->dropColumn('requester_seen_at'));
    }
};
