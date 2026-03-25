<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE warehouse_batch_logs MODIFY COLUMN event_type VARCHAR(50) NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE warehouse_batch_logs ALTER COLUMN event_type TYPE VARCHAR(50)");
            DB::statement("ALTER TABLE warehouse_batch_logs DROP CONSTRAINT IF EXISTS warehouse_batch_logs_event_type_check");
            DB::statement("ALTER TABLE warehouse_batch_logs ALTER COLUMN event_type SET NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE warehouse_batch_logs MODIFY COLUMN event_type ENUM(
            'CONDITION_UPDATED','QUANTITY_ADJUSTED','TRANSFERRED','CULLED','SCANNED','NOTE'
        ) NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE warehouse_batch_logs ALTER COLUMN event_type TYPE VARCHAR(50)");
            DB::statement("ALTER TABLE warehouse_batch_logs DROP CONSTRAINT IF EXISTS warehouse_batch_logs_event_type_check");
            DB::statement("ALTER TABLE warehouse_batch_logs ADD CONSTRAINT warehouse_batch_logs_event_type_check CHECK (event_type IN ('CONDITION_UPDATED','QUANTITY_ADJUSTED','TRANSFERRED','CULLED','SCANNED','NOTE'))");
            DB::statement("ALTER TABLE warehouse_batch_logs ALTER COLUMN event_type SET NOT NULL");
        }
    }
};