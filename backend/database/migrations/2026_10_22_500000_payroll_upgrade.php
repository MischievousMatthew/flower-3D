<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll: allowances, bonuses and deductions on each payslip, recurring pay items per person,
 * a salary history, and who created / approved / paid each payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payrolls')) {
            Schema::table('payrolls', function (Blueprint $table) {
                if (! Schema::hasColumn('payrolls', 'allowances_amount')) {
                    $table->decimal('allowances_amount', 12, 2)->default(0);
                    $table->decimal('bonuses_amount', 12, 2)->default(0);
                    $table->decimal('other_deductions_amount', 12, 2)->default(0);
                    $table->decimal('contributions_share', 6, 4)->nullable(); // share of the monthly contributions taken
                    $table->unsignedInteger('late_minutes')->default(0);
                    $table->unsignedInteger('undertime_minutes')->default(0);
                    $table->string('created_by_name', 150)->nullable();
                    $table->unsignedBigInteger('created_by_employee_id')->nullable();
                    $table->string('approved_by_name', 150)->nullable();
                    $table->timestamp('approved_at')->nullable();
                    $table->string('paid_by_name', 150)->nullable();
                }
            });
        }

        if (! Schema::hasTable('payroll_items')) {
            Schema::create('payroll_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
                $table->string('kind', 20); // allowance | bonus | deduction
                $table->string('label', 80);
                $table->decimal('amount', 12, 2);
                $table->string('source', 20)->default('manual'); // recurring | manual
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pay_components')) {
            Schema::create('pay_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('employee_info_id')->constrained('employees_info')->cascadeOnDelete();
                $table->string('kind', 20); // allowance | deduction
                $table->string('label', 80);
                $table->decimal('amount', 12, 2);
                $table->string('basis', 20)->default('per_payroll'); // per_payroll | per_day
                $table->boolean('active')->default(true);
                $table->string('note', 255)->nullable();
                $table->string('created_by', 150)->nullable();
                $table->timestamps();
                $table->index(['owner_id', 'employee_info_id']);
            });
        }

        if (! Schema::hasTable('salary_records')) {
            Schema::create('salary_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('employee_info_id')->constrained('employees_info')->cascadeOnDelete();
                $table->decimal('basic_salary', 12, 2)->nullable();
                $table->string('salary_type', 20)->nullable();
                $table->date('effective_from');
                $table->string('changed_by', 150)->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamps();
                $table->index(['owner_id', 'employee_info_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_records');
        Schema::dropIfExists('pay_components');
        Schema::dropIfExists('payroll_items');
        if (Schema::hasTable('payrolls') && Schema::hasColumn('payrolls', 'allowances_amount')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn(['allowances_amount', 'bonuses_amount', 'other_deductions_amount', 'contributions_share', 'late_minutes',
                    'undertime_minutes', 'created_by_name', 'created_by_employee_id', 'approved_by_name', 'approved_at', 'paid_by_name']);
            });
        }
    }
};
