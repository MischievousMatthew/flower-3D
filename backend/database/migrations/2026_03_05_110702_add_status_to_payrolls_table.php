<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'paid'
            ])->default('pending')->after('notes');
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->text('finance_notes')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'paid_at',
                'finance_notes',
            ]);
        });
    }
};