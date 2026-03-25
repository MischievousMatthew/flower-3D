<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees_info', function (Blueprint $table) {
            // Add new payroll-related columns
            $table->decimal('standard_work_hours_per_day', 4, 2)
                ->default(8.00)
                ->comment('Standard work hours per day (e.g., 8.00)');

            $table->smallInteger('working_days_per_week')
                ->default(5)
                ->comment('Working days per week (e.g., 5 for Mon-Fri)');

            $table->smallInteger('working_days_per_month')
                ->default(22)
                ->comment('Working days per month (e.g., 22)');
        });

        // Update salary_type column for PostgreSQL
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Use a CHECK constraint instead of ENUM
            DB::statement("
                ALTER TABLE employees_info
                DROP CONSTRAINT IF EXISTS salary_type_check;
            ");

            DB::statement("
                ALTER TABLE employees_info
                ADD CONSTRAINT salary_type_check
                CHECK (salary_type IN ('daily', 'weekly', 'monthly'));
            ");
        }
    }

    public function down(): void
    {
        Schema::table('employees_info', function (Blueprint $table) {
            $table->dropColumn([
                'standard_work_hours_per_day',
                'working_days_per_week',
                'working_days_per_month'
            ]);
        });

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE employees_info
                DROP CONSTRAINT IF EXISTS salary_type_check;
            ");
        }
    }
};