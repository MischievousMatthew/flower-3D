<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The vendor application offers "Cooperative" (registered with the CDA), but
 * the table's business_type check still allowed only individual, partnership
 * and corporation, so every cooperative's application failed when it was
 * saved, after all its documents had been uploaded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->allow(['individual', 'partnership', 'corporation', 'cooperative']);
    }

    public function down(): void
    {
        $this->allow(['individual', 'partnership', 'corporation']);
    }

    private function allow(array $types): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('vendor_applications')) {
            return;
        }
        $list = implode(', ', array_map(fn ($t) => "'{$t}'", $types));
        DB::statement('ALTER TABLE vendor_applications DROP CONSTRAINT IF EXISTS vendor_applications_business_type_check');
        DB::statement("ALTER TABLE vendor_applications ADD CONSTRAINT vendor_applications_business_type_check CHECK (business_type IN ({$list}))");
    }
};
