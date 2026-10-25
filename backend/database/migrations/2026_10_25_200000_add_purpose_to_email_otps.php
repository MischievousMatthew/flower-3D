<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an email code was sent for (signup, reset, signin, verify), so a code
 * only works for that: a password-reset code can't finish a two-factor sign-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_otps') && ! Schema::hasColumn('email_otps', 'purpose')) {
            Schema::table('email_otps', function (Blueprint $table) {
                $table->string('purpose', 12)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('email_otps', 'purpose')) {
            Schema::table('email_otps', fn (Blueprint $table) => $table->dropColumn('purpose'));
        }
    }
};
