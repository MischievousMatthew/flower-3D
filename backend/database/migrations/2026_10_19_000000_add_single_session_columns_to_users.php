<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One sign-in per account: when the session was last used, which browser
 * holds it, and the fingerprint of the session a new sign-in replaced (so
 * that browser can be told why it was signed out).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'session_device')) {
                $table->string('session_device', 64)->nullable();
            }
            if (!Schema::hasColumn('users', 'replaced_token_hash')) {
                $table->string('replaced_token_hash', 64)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['replaced_token_hash']);
            $table->dropColumn(['last_seen_at', 'session_device', 'replaced_token_hash']);
        });
    }
};
