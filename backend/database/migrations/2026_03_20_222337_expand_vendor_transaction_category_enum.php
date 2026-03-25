<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE vendor_transactions MODIFY COLUMN category ENUM('order_revenue','refund','adjustment','procurement','payroll') NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE vendor_transactions ALTER COLUMN category TYPE VARCHAR(255)");
            DB::statement("ALTER TABLE vendor_transactions DROP CONSTRAINT IF EXISTS vendor_transactions_category_check");
            DB::statement("ALTER TABLE vendor_transactions ADD CONSTRAINT vendor_transactions_category_check CHECK (category IN ('order_revenue','refund','adjustment','procurement','payroll'))");
            DB::statement("ALTER TABLE vendor_transactions ALTER COLUMN category SET NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE vendor_transactions MODIFY COLUMN category ENUM('order_revenue','refund','adjustment') NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE vendor_transactions ALTER COLUMN category TYPE VARCHAR(255)");
            DB::statement("ALTER TABLE vendor_transactions DROP CONSTRAINT IF EXISTS vendor_transactions_category_check");
            DB::statement("ALTER TABLE vendor_transactions ADD CONSTRAINT vendor_transactions_category_check CHECK (category IN ('order_revenue','refund','adjustment'))");
            DB::statement("ALTER TABLE vendor_transactions ALTER COLUMN category SET NOT NULL");
        }
    }
};