<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos in Help & support conversations (a wilted bouquet, a screenshot of
 * the problem), from the person asking, the shop and BloomCraft support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('support_messages', 'attachments')) {
                $table->json('attachments')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', fn (Blueprint $t) => $t->dropColumn('attachments'));
    }
};
