<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('owner_id')
                ->constrained('employees') // owner is the employee (HR/manager table) if applicable
                ->onDelete('cascade')
                ->comment('Owner of the employee');

            $table->foreignId('employee_id')
                ->constrained('employees_info')
                ->onDelete('cascade')
                ->comment('Employee taking leave');

            // Leave date range
            $table->date('start_date')->comment('Leave start date');
            $table->date('end_date')->comment('Leave end date');
            $table->integer('total_days')->comment('Total leave days (excluding rest days)');

            // Leave details
            $table->enum('leave_type', [
                'sick_leave',
                'vacation_leave',
                'emergency_leave',
                'unpaid_leave',
                'maternity_leave',
                'paternity_leave',
                'bereavement_leave',
                'other'
            ])->comment('Type of leave');

            $table->boolean('is_paid')->default(true)->comment('Whether this leave type is paid');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable()->comment('Reason for leave');
            $table->text('admin_notes')->nullable()->comment('Admin notes');

            // Reviewer (HR) tracking
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('employees') // ✅ reviewer is in employees table
                ->onDelete('set null')
                ->comment('Employee who approved/rejected leave');
            $table->timestamp('reviewed_at')->nullable();

            // Submission tracking
            $table->string('submission_token')->unique()->comment('Unique submission token');
            $table->string('submission_ip', 45)->nullable()->comment('IP of submission');
            $table->string('submission_device')->nullable()->comment('Device/user agent');
            $table->timestamp('submitted_at')->nullable()->comment('When leave was submitted');

            $table->timestamps();

            // Indexes
            $table->index(['employee_id', 'start_date', 'end_date']);
            $table->index(['status', 'start_date', 'end_date']);
            $table->index('submission_token');
        });

        // Rate limiting table for leave requests
        Schema::create('leave_request_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                  ->constrained('employees_info')
                  ->onDelete('cascade');
            $table->string('ip_address', 45);
            $table->timestamp('attempted_at');

            $table->index(['employee_id', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_attempts');
        Schema::dropIfExists('employee_leaves');
    }
};