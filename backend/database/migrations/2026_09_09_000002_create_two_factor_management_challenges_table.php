<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_management_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_token_hash', 64);
            $table->timestamp('email_otp_verified_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->unsignedTinyInteger('passkey_attempts')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'session_token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_management_challenges');
    }
};
