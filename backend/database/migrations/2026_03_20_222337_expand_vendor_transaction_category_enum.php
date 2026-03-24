<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE vendor_transactions MODIFY COLUMN category ENUM('order_revenue','refund','adjustment','procurement','payroll') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vendor_transactions MODIFY COLUMN category ENUM('order_revenue','refund','adjustment') NOT NULL");
    }
};