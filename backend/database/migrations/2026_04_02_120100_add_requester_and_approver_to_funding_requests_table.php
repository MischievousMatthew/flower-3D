<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funding_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('funding_requests', 'requester_id')) {
                $table->foreignId('requester_id')
                    ->nullable()
                    ->after('submitted_by_employee_id')
                    ->constrained('employees')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('funding_requests', 'approver_id')) {
                $table->foreignId('approver_id')
                    ->nullable()
                    ->after('accounting_manager_id')
                    ->constrained('employees')
                    ->nullOnDelete();
            }
        });

        DB::table('funding_requests')
            ->whereNull('requester_id')
            ->update([
                'requester_id' => DB::raw('submitted_by_employee_id'),
            ]);

        DB::table('funding_requests')
            ->whereNull('approver_id')
            ->update([
                'approver_id' => DB::raw('accounting_manager_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('funding_requests', function (Blueprint $table) {
            if (Schema::hasColumn('funding_requests', 'approver_id')) {
                $table->dropForeign(['approver_id']);
                $table->dropColumn('approver_id');
            }

            if (Schema::hasColumn('funding_requests', 'requester_id')) {
                $table->dropForeign(['requester_id']);
                $table->dropColumn('requester_id');
            }
        });
    }
};
