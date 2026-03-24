<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('owner_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Vendor owner');
            
            $table->foreignId('employee_id')
                ->constrained('employees_info')
                ->onDelete('cascade')
                ->comment('Employee receiving payroll');
            
            // Payroll Period
            $table->date('period_start')->comment('Start date of payroll period');
            $table->date('period_end')->comment('End date of payroll period');
            
            // Salary Configuration (snapshot at time of payroll generation)
            $table->enum('salary_type', ['daily', 'weekly', 'monthly'])
                ->comment('Type of salary calculation');
            
            $table->decimal('basic_salary', 12, 2)
                ->comment('Basic salary amount');
            
            $table->decimal('standard_work_hours_per_day', 4, 2)
                ->comment('Standard hours per day');
            
            $table->tinyInteger('working_days_per_week')
                ->nullable()
                ->comment('For weekly salary type');
            
            $table->tinyInteger('working_days_per_month')
                ->nullable()
                ->comment('For monthly salary type');
            
            // Attendance & Work Summary
            $table->decimal('total_hours_worked', 8, 2)
                ->comment('Total hours from attendance records');
            
            $table->integer('attendance_records_count')
                ->default(0)
                ->comment('Number of attendance records used');
            
            $table->integer('expected_work_days')
                ->default(0)
                ->comment('Expected working days for the period');
            
            $table->integer('actual_work_days')
                ->default(0)
                ->comment('Actual days worked');
            
            $table->integer('paid_leave_days')
                ->default(0)
                ->comment('Paid leave days');
            
            $table->integer('unpaid_leave_days')
                ->default(0)
                ->comment('Unpaid leave days');
            
            $table->integer('absent_days')
                ->default(0)
                ->comment('Absent days');
            
            // Salary Calculations
            $table->decimal('hourly_rate', 10, 2)
                ->comment('Calculated hourly rate');
            
            $table->decimal('gross_salary', 12, 2)
                ->comment('Calculated gross salary');
            
            $table->decimal('deduction_amount', 10, 2)
                ->default(0)
                ->comment('Total deduction amount');
            
            $table->decimal('net_salary', 12, 2)
                ->default(0)
                ->comment('Final net salary after deductions');
            
            // Metadata
            $table->text('notes')
                ->nullable()
                ->comment('Additional notes');
            
            $table->timestamps();
            
            // Prevent duplicate payroll for same period
            $table->unique(
                ['owner_id', 'employee_id', 'period_start', 'period_end'],
                'unique_payroll_period'
            );
            
            // Indexes
            $table->index('owner_id');
            $table->index('employee_id');
            $table->index(['period_start', 'period_end']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};