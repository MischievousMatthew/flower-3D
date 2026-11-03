<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a sign-in stopped working (signed in elsewhere, password changed,
 * suspended…), so the browser still holding it can tell the person instead
 * of silently going back to the login page. Kept for a week.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ended_sessions')) {
            return;
        }
        Schema::create('ended_sessions', function (Blueprint $table) {
            $table->string('token_hash', 64)->primary();
            $table->string('code', 40);
            $table->string('message', 300);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ended_sessions');
    }
};
