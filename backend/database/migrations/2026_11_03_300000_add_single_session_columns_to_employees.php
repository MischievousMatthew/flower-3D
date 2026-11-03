<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP staff sign in on one browser at a time, like everyone else: signing in
 * on a second one while the first is in use asks before taking over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
            if (! Schema::hasColumn('employees', 'session_device')) {
                $table->string('session_device', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['last_seen_at', 'session_device']);
        });
    }
};
