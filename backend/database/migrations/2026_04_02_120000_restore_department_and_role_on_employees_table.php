<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'role')) {
                $table->string('role')->nullable()->after('username');
            }

            if (!Schema::hasColumn('employees', 'department')) {
                $table->string('department')->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'department')) {
                $table->dropColumn('department');
            }

            if (Schema::hasColumn('employees', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
