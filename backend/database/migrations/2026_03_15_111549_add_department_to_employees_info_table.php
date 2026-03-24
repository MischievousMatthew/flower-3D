<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees_info', function (Blueprint $table) {
            // Add after reporting_manager to keep the column order logical
            $table->string('department', 150)->nullable()->after('reporting_manager');
        });
    }

    public function down(): void
    {
        Schema::table('employees_info', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};