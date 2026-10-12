<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Logs keep a readable name for what was touched, so pages never show IDs or API paths */
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->string('target_label', 190)->nullable()->after('target_id');
        });
        Schema::table('api_error_logs', function (Blueprint $table) {
            $table->string('action', 190)->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', fn (Blueprint $table) => $table->dropColumn('target_label'));
        Schema::table('api_error_logs', fn (Blueprint $table) => $table->dropColumn('action'));
    }
};
