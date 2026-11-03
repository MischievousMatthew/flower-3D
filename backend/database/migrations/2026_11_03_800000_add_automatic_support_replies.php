<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Help & support answers every request straight away with an automatic reply
 * (reference, who handles it, when to expect an answer) and follows up once
 * if nobody has replied in time. Automatic replies are marked so they never
 * count as the shop's or support's answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('support_messages', 'is_automated')) {
                $table->boolean('is_automated')->default(false);
            }
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'followed_up_at')) {
                $table->timestamp('followed_up_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', fn (Blueprint $t) => $t->dropColumn('is_automated'));
        Schema::table('support_tickets', fn (Blueprint $t) => $t->dropColumn('followed_up_at'));
    }
};
