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
            if (! Schema::hasColumn('funding_requests', 'payment_status')) {
                $table->string('payment_status')->default('unpaid')->after('request_status');
            }

            if (! Schema::hasColumn('funding_requests', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_status');
            }
        });

        DB::table('funding_requests')
            ->where('request_status', 'Approved')
            ->where(function ($query) {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', 'unpaid');
            })
            ->update([
                'payment_status' => 'paid',
                'paid_at' => DB::raw('COALESCE(accounting_decision_at, updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('funding_requests', function (Blueprint $table) {
            if (Schema::hasColumn('funding_requests', 'paid_at')) {
                $table->dropColumn('paid_at');
            }

            if (Schema::hasColumn('funding_requests', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });
    }
};
