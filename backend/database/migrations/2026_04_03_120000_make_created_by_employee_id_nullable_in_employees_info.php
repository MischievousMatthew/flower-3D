<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees_info') || !Schema::hasColumn('employees_info', 'created_by_employee_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE employees_info MODIFY created_by_employee_id BIGINT UNSIGNED NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE employees_info ALTER COLUMN created_by_employee_id DROP NOT NULL');
            return;
        }

        Schema::table('employees_info', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_employee_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees_info') || !Schema::hasColumn('employees_info', 'created_by_employee_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE employees_info MODIFY created_by_employee_id BIGINT UNSIGNED NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE employees_info ALTER COLUMN created_by_employee_id SET NOT NULL');
            return;
        }

        Schema::table('employees_info', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_employee_id')->nullable(false)->change();
        });
    }
};
