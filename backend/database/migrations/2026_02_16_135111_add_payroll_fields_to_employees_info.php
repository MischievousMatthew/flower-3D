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
                ->after('allowance')
                ->comment('Standard work hours per day (e.g., 8.00)');
            
            $table->tinyInteger('working_days_per_week')
                ->default(5)
                ->after('standard_work_hours_per_day')
                ->comment('Working days per week (e.g., 5 for Mon-Fri)');
            
            $table->tinyInteger('working_days_per_month')
                ->default(22)
                ->after('working_days_per_week')
                ->comment('Working days per month (e.g., 22)');
        });

        // Update salary_type enum to match payroll requirements
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE employees_info MODIFY COLUMN salary_type ENUM('daily', 'weekly', 'monthly') DEFAULT NULL");
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
        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE employees_info MODIFY COLUMN salary_type ENUM('Monthly', 'Daily', 'Hourly') DEFAULT NULL");
        }
    }
};