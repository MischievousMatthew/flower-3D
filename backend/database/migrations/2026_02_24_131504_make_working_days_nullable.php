<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees_info', function (Blueprint $table) {
            $table->integer('working_days_per_week')->nullable()->change();
            $table->integer('working_days_per_month')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees_info', function (Blueprint $table) {
            $table->integer('working_days_per_week')->nullable(false)->change();
            $table->integer('working_days_per_month')->nullable(false)->change();
        });
    }
};
