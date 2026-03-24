<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('funding_requests', function (Blueprint $table) {
            $table->foreignId('linked_order_id')
                ->nullable()
                ->after('reviewed_by_employee_id')
                ->constrained('purchase_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('funding_requests', function (Blueprint $table) {
            $table->dropForeign(['linked_order_id']);
            $table->dropColumn('linked_order_id');
        });
    }
};
