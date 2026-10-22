<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Payroll tables for the SQLite test database (with the columns production has). Use after CreatesAttendanceTables. */
trait CreatesPayrollTables
{
    protected function createPayrollTables(): void
    {
        foreach (['request_events', 'salary_records', 'pay_components', 'payroll_items', 'payrolls', 'employee_leaves'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('employees_info', function (Blueprint $t) {
            foreach (['basic_salary' => 'decimal', 'standard_work_hours_per_day' => 'decimal', 'allowance' => 'decimal'] as $c => $type) {
                if (! Schema::hasColumn('employees_info', $c)) {
                    $t->decimal($c, 12, 2)->nullable();
                }
            }
            foreach (['salary_type', 'payment_method', 'bank_name', 'account_number', 'tax_status', 'department'] as $c) {
                if (! Schema::hasColumn('employees_info', $c)) {
                    $t->string($c)->nullable();
                }
            }
            foreach (['working_days_per_week', 'working_days_per_month'] as $c) {
                if (! Schema::hasColumn('employees_info', $c)) {
                    $t->integer($c)->nullable();
                }
            }
        });
        Schema::create('employee_leaves', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('employee_id');
            $t->date('start_date');
            $t->date('end_date');
            $t->integer('total_days')->default(1);
            $t->string('leave_type')->default('vacation_leave');
            $t->boolean('is_paid')->default(true);
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('employee_id');
            $t->date('period_start');
            $t->date('period_end');
            $t->string('salary_type')->nullable();
            foreach (['basic_salary', 'standard_work_hours_per_day', 'total_hours_worked', 'hourly_rate', 'gross_salary', 'deduction_amount', 'net_salary',
                'actual_work_amount', 'paid_leave_amount', 'overtime_hours', 'overtime_amount', 'sss_contribution', 'philhealth_contribution', 'pagibig_contribution'] as $c) {
                $t->decimal($c, 12, 2)->default(0);
            }
            foreach (['working_days_per_week', 'working_days_per_month', 'attendance_records_count', 'expected_work_days', 'actual_work_days', 'paid_leave_days', 'unpaid_leave_days', 'absent_days'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->text('notes')->nullable();
            $t->string('status')->default('pending');
            $t->timestamp('paid_at')->nullable();
            $t->text('finance_notes')->nullable();
            $t->boolean('include_contributions')->default(false);
            $t->timestamps();
        });
        Schema::create('request_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('subject_type', 30);
            $t->unsignedBigInteger('subject_id');
            $t->string('action', 30);
            $t->string('actor_name', 150)->nullable();
            $t->string('note', 1000)->nullable();
            $t->timestamp('created_at')->nullable();
        });
        (require base_path('database/migrations/2026_10_22_500000_payroll_upgrade.php'))->up();
    }
}
