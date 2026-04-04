<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALLOWED_STATUSES = [
        'Probationary',
        'Regular',
        'Contractual',
        'Part-time',
        'Active',
        'Inactive',
    ];

    public function up(): void
    {
        $this->syncConstraint(self::ALLOWED_STATUSES);
    }

    public function down(): void
    {
        $this->syncConstraint([
            'Probationary',
            'Regular',
            'Contractual',
            'Part-time',
        ]);
    }

    private function syncConstraint(array $statuses): void
    {
        if (!Schema::hasTable('employees_info') || !Schema::hasColumn('employees_info', 'employment_status')) {
            return;
        }

        $driver = DB::getDriverName();
        $quotedStatuses = implode(', ', array_map(
            fn (string $status) => "'" . str_replace("'", "''", $status) . "'",
            $statuses
        ));

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE employees_info MODIFY COLUMN employment_status ENUM({$quotedStatuses})");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE employees_info ALTER COLUMN employment_status TYPE VARCHAR(255)");
            DB::statement("ALTER TABLE employees_info DROP CONSTRAINT IF EXISTS employees_info_employment_status_check");
            DB::statement("ALTER TABLE employees_info ADD CONSTRAINT employees_info_employment_status_check CHECK (employment_status IN ({$quotedStatuses}))");
        }
    }
};
