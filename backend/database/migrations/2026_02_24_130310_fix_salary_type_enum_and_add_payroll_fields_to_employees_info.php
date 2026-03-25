<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add new payroll configuration columns SAFELY
        Schema::table('employees_info', function (Blueprint $table) {

            if (!Schema::hasColumn('employees_info', 'standard_work_hours_per_day')) {
                $table->decimal('standard_work_hours_per_day', 5, 2)
                    ->nullable()
                    ->default(8.00)
                    ->after('rest_days')
                    ->comment('Standard work hours per day (e.g., 8.00)');
            }

            if (!Schema::hasColumn('employees_info', 'working_days_per_week')) {
                $table->integer('working_days_per_week')
                    ->nullable()
                    ->default(5)
                    ->after('standard_work_hours_per_day')
                    ->comment('Working days per week for weekly salary type');
            }

            if (!Schema::hasColumn('employees_info', 'working_days_per_month')) {
                $table->integer('working_days_per_month')
                    ->nullable()
                    ->default(22)
                    ->after('working_days_per_week')
                    ->comment('Working days per month for monthly salary type');
            }
        });

        // Only convert salary_type if it exists and not already lowercase enum
        if (Schema::hasColumn('employees_info', 'salary_type')) {

            Schema::table('employees_info', function (Blueprint $table) {
                if (!Schema::hasColumn('employees_info', 'salary_type_temp')) {
                    $table->string('salary_type_temp', 20)
                        ->nullable()
                        ->after('basic_salary');
                }
            });

            DB::statement("
                UPDATE employees_info 
                SET salary_type_temp = CASE 
                    WHEN salary_type = 'Monthly' THEN 'monthly'
                    WHEN salary_type = 'Daily' THEN 'daily'
                    WHEN salary_type = 'Hourly' THEN 'daily'
                    WHEN salary_type = 'weekly' THEN 'weekly'
                    WHEN salary_type = 'monthly' THEN 'monthly'
                    WHEN salary_type = 'daily' THEN 'daily'
                    ELSE NULL
                END
            ");

            Schema::table('employees_info', function (Blueprint $table) {
                $table->dropColumn('salary_type');
            });

            Schema::table('employees_info', function (Blueprint $table) {
                $table->renameColumn('salary_type_temp', 'salary_type');
            });

            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE employees_info MODIFY COLUMN salary_type ENUM('daily', 'weekly', 'monthly') NULL");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE employees_info ALTER COLUMN salary_type TYPE VARCHAR(255)");
                DB::statement("ALTER TABLE employees_info DROP CONSTRAINT IF EXISTS employees_info_salary_type_check");
                DB::statement("ALTER TABLE employees_info ADD CONSTRAINT employees_info_salary_type_check CHECK (salary_type IN ('daily', 'weekly', 'monthly'))");
                DB::statement("ALTER TABLE employees_info ALTER COLUMN salary_type DROP NOT NULL");
            }
        }
    }

    public function down(): void
    {
        // Reverse Step 6: Add temp column
        Schema::table('employees_info', function (Blueprint $table) {
            $table->string('salary_type_temp', 20)->nullable()->after('basic_salary');
        });

        // Reverse Step 5 & 4: Convert back to capitalized
        DB::statement("
            UPDATE employees_info 
            SET salary_type_temp = CASE 
                WHEN salary_type = 'monthly' THEN 'Monthly'
                WHEN salary_type = 'daily' THEN 'Daily'
                WHEN salary_type = 'weekly' THEN 'Daily'
                ELSE NULL
            END
        ");

        Schema::table('employees_info', function (Blueprint $table) {
            $table->dropColumn('salary_type');
        });

        Schema::table('employees_info', function (Blueprint $table) {
            $table->renameColumn('salary_type_temp', 'salary_type');
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE employees_info MODIFY COLUMN salary_type ENUM('Monthly', 'Daily', 'Hourly') NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE employees_info ALTER COLUMN salary_type TYPE VARCHAR(255)");
            DB::statement("ALTER TABLE employees_info DROP CONSTRAINT IF EXISTS employees_info_salary_type_check");
            DB::statement("ALTER TABLE employees_info ADD CONSTRAINT employees_info_salary_type_check CHECK (salary_type IN ('Monthly', 'Daily', 'Hourly'))");
            DB::statement("ALTER TABLE employees_info ALTER COLUMN salary_type DROP NOT NULL");
        }

        // Reverse Step 1: Drop new columns
        Schema::table('employees_info', function (Blueprint $table) {
            $table->dropColumn([
                'standard_work_hours_per_day',
                'working_days_per_week',
                'working_days_per_month'
            ]);
        });
    }
};