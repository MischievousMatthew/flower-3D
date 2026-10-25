<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The tables sign-in, sign-up and password reset touch */
trait CreatesAuthTables
{
    protected function createAuthTables(): void
    {
        foreach (['login_attempts', 'login_audit_logs', 'email_otps', 'employees', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('surname')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('contact_number')->nullable();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->json('vendor_data')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_suspended')->default(false);
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('api_token', 80)->nullable()->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('session_device', 64)->nullable();
            $table->string('replaced_token_hash', 64)->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('Active');
            $table->string('api_token', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 190)->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_type', 20)->default('user');
            $table->string('reason', 40);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('login_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('logged_in_at')->nullable();
        });
        Schema::create('email_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('purpose', 12)->nullable();
            $table->timestamps();
        });
        Schema::dropIfExists('site_settings');
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
        // Settings are cached per process; start every test from the defaults
        (new \ReflectionProperty(\App\Support\PlatformSettings::class, 'cache'))->setValue(null, null);
        Schema::dropIfExists('two_factor_login_challenges');
        Schema::create('two_factor_login_challenges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('challenge_token_hash', 64)->unique();
            $table->unsignedTinyInteger('passkey_attempts')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }
}
