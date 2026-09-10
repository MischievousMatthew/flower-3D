<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'two_factor_passkey_hash')) {
                $table->string('two_factor_passkey_hash')->nullable()->after('api_token');
            }

            if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('two_factor_passkey_hash');
            }
        });

        Schema::table('two_factor_management_challenges', function (Blueprint $table) {
            if (!Schema::hasColumn('two_factor_management_challenges', 'action')) {
                $table->string('action', 16)->default('enable')->after('session_token_hash');
            }
        });
    }

    public function down(): void
    {
        // This is a compatibility migration for databases that had already
        // recorded the original 2FA migrations. Do not drop shared columns.
    }
};
