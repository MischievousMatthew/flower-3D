<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Errors people saw get a reference code they can quote to support, plus
 * which kind of account hit it and what they were shown, so admins can
 * find the real error behind a report.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_error_logs')) {
            return;
        }
        Schema::table('api_error_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('api_error_logs', 'reference')) {
                $table->string('reference', 12)->nullable()->index();
            }
            if (! Schema::hasColumn('api_error_logs', 'account_type')) {
                // customer, vendor, staff, admin or guest
                $table->string('account_type', 12)->nullable();
            }
            if (! Schema::hasColumn('api_error_logs', 'account_name')) {
                $table->string('account_name', 120)->nullable();
            }
            if (! Schema::hasColumn('api_error_logs', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable();
            }
            if (! Schema::hasColumn('api_error_logs', 'location')) {
                // File and line in the code, e.g. "CrmDashboardController.php:171"
                $table->string('location', 190)->nullable();
            }
            if (! Schema::hasColumn('api_error_logs', 'shown_message')) {
                $table->string('shown_message', 300)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('api_error_logs')) {
            return;
        }
        Schema::table('api_error_logs', function (Blueprint $table) {
            $table->dropColumn(['reference', 'account_type', 'account_name', 'employee_id', 'location', 'shown_message']);
        });
    }
};
