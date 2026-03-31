<?php
// database/migrations/xxxx_add_government_contributions_to_payrolls_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->boolean('include_contributions')->default(false)->after('notes');
            $table->decimal('sss_contribution', 10, 2)->default(0)->after('include_contributions');
            $table->decimal('philhealth_contribution', 10, 2)->default(0)->after('sss_contribution');
            $table->decimal('pagibig_contribution', 10, 2)->default(0)->after('philhealth_contribution');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['include_contributions', 'sss_contribution', 'philhealth_contribution', 'pagibig_contribution']);
        });
    }
};