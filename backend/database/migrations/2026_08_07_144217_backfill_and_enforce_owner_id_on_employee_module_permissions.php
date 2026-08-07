<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill owner_id for rows created via raw DB::table() inserts
        // (e.g. the earlier permission-splitting migration), which bypassed
        // the BelongsToOwner::creating() hook and left owner_id null.
        DB::statement('
            UPDATE employee_module_permissions emp
            SET owner_id = e.owner_id
            FROM employees e
            WHERE emp.employee_id = e.id
              AND emp.owner_id IS NULL
        ');

        Schema::table('employee_module_permissions', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_module_permissions', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->change();
        });
    }
};