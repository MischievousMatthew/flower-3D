<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support changing columns easily, so we handle it for different drivers
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE employees_info MODIFY COLUMN employment_status ENUM('Probationary', 'Regular', 'Contractual', 'Part-time', 'Active', 'Inactive')");
        }
        // For SQLite (common in local dev often, though user seems to be on Windows/Laravel)
        // we might not actually need to do anything as SQLite doesn't strictly enforce enums
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE employees_info MODIFY COLUMN employment_status ENUM('Probationary', 'Regular', 'Contractual', 'Part-time')");
        }
    }
};
