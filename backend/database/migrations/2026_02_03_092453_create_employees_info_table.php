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
        Schema::create('employees_info', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('owner_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Vendor owner who owns this employee record');
            
            $table->foreignId('created_by_employee_id')
                ->constrained('employees')
                ->onDelete('cascade')
                ->comment('HR employee who created this record');
            
            // Employee ID (scoped to owner)
            $table->string('employee_id', 20)->comment('Format: EMP-10001, unique per owner');
            
            // Basic Information
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->date('date_of_birth');
            $table->enum('civil_status', ['Single', 'Married', 'Divorced', 'Widowed']);
            $table->string('avatar')->nullable();
            
            // Contact Information
            $table->string('personal_email', 150);
            $table->string('work_email', 150);
            $table->string('mobile_number', 20);
            $table->text('address');
            
            // Emergency Contact
            $table->string('emergency_contact_name', 150);
            $table->string('emergency_contact_number', 20);
            $table->string('emergency_relationship', 100);
            
            // Employment Details
            $table->enum('employment_status', ['Probationary', 'Regular', 'Contractual', 'Part-time']);
            $table->string('position', 150);
            $table->string('department', 150);
            $table->enum('employment_type', ['Full-time', 'Part-time']);
            $table->date('date_hired');
            $table->string('work_location', 200);
            $table->string('reporting_manager', 150);
            
            // Work Schedule
            $table->enum('work_schedule', ['Fixed', 'Shifting']);
            $table->time('shift_start');
            $table->time('shift_end');
            $table->string('rest_days', 100)->comment('e.g., Saturday, Sunday');
            
            // Payroll Information (Optional)
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->enum('salary_type', ['Monthly', 'Daily', 'Hourly'])->nullable();
            $table->enum('payment_method', ['Bank', 'Cash'])->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('tax_status', 20)->nullable()->comment('e.g., S, ME, ME1, ME2');
            $table->decimal('allowance', 12, 2)->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Composite unique constraint: employee_id must be unique per owner
            $table->unique(['owner_id', 'employee_id'], 'unique_employee_id_per_owner');
            
            // Indexes for better query performance
            $table->index('owner_id');
            $table->index('created_by_employee_id');
            $table->index('employee_id');
            $table->index('employment_status');
            $table->index('department');
            $table->index('work_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees_info');
    }
};