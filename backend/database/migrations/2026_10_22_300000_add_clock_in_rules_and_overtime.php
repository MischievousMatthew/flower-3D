<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time in only once the scheduled shift has started, and overtime that HR approves before
 * it's paid. Settings live with the shop's other attendance rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_settings', 'early_clock_in_minutes')) {
                $table->unsignedSmallInteger('early_clock_in_minutes')->default(0);
                $table->unsignedSmallInteger('overtime_min_minutes')->default(15);
                $table->decimal('overtime_rate', 4, 2)->default(1.25);
            }
        });

        Schema::table('employee_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_attendance', 'overtime_minutes')) {
                $table->unsignedSmallInteger('overtime_minutes')->nullable();
                $table->string('overtime_status', 20)->nullable()->index(); // pending | approved | rejected
                $table->unsignedSmallInteger('overtime_approved_minutes')->nullable();
                $table->string('overtime_reviewed_by', 150)->nullable();
                $table->timestamp('overtime_reviewed_at')->nullable();
                $table->string('overtime_note', 500)->nullable();
            }
        });

        if (Schema::hasTable('payrolls')) {
            Schema::table('payrolls', function (Blueprint $table) {
                if (! Schema::hasColumn('payrolls', 'overtime_hours')) {
                    $table->decimal('overtime_hours', 8, 2)->default(0);
                    $table->decimal('overtime_amount', 12, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payrolls')) {
            Schema::table('payrolls', function (Blueprint $table) {
                if (Schema::hasColumn('payrolls', 'overtime_hours')) {
                    $table->dropColumn(['overtime_hours', 'overtime_amount']);
                }
            });
        }
        Schema::table('employee_attendance', function (Blueprint $table) {
            if (Schema::hasColumn('employee_attendance', 'overtime_minutes')) {
                $table->dropIndex(['overtime_status']);
                $table->dropColumn(['overtime_minutes', 'overtime_status', 'overtime_approved_minutes', 'overtime_reviewed_by', 'overtime_reviewed_at', 'overtime_note']);
            }
        });
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_settings', 'early_clock_in_minutes')) {
                $table->dropColumn(['early_clock_in_minutes', 'overtime_min_minutes', 'overtime_rate']);
            }
        });
    }
};
