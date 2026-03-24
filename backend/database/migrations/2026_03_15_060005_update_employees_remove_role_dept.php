<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove role and department string columns from employees table.
        // These are now managed via the employee_assignments pivot table.
        Schema::table('employees', function (Blueprint $table) {
            // Drop the composite index that references 'department' first
            $table->dropIndex(['owner_id', 'department']);
            $table->dropColumn(['department', 'role']);
        });

        // Remove the department string column from employees_info table.
        // Department context is now resolved through employee_assignments.
        if (Schema::hasColumn('employees_info', 'department')) {
            Schema::table('employees_info', function (Blueprint $table) {
                $table->dropIndex(['department']);
                $table->dropColumn('department');
            });
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('role')->nullable()->after('username');
            $table->string('department')->nullable()->after('role');
            $table->index(['owner_id', 'department']);
        });

        Schema::table('employees_info', function (Blueprint $table) {
            $table->string('department', 150)->nullable()->after('position');
            $table->index('department');
        });
    }
};
