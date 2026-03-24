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
        Schema::create('employee_attendance', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('owner_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Vendor owner');
            
            $table->foreignId('employee_id')
                ->constrained('employees_info')
                ->onDelete('cascade')
                ->comment('Employee from employees_info table');
            
            // Attendance Data
            $table->date('attendance_date')->comment('Date of attendance (YYYY-MM-DD)');
            $table->string('day', 20)->comment('Day name (e.g., Monday)');
            $table->tinyInteger('month')->comment('Month number (1-12)');
            $table->year('year')->comment('Year');
            
            // Time Records
            $table->time('time_in')->comment('Time when employee clocked in');
            $table->time('time_out')->nullable()->comment('Time when employee clocked out');
            
            // Additional Info
            $table->decimal('total_hours', 5, 2)->nullable()->comment('Total hours worked');
            $table->enum('status', ['incomplete', 'complete'])->default('incomplete');
            $table->text('notes')->nullable()->comment('Admin notes or remarks');
            
            $table->timestamps();
            
            // Composite unique constraint: one record per employee per day
            $table->unique(['owner_id', 'employee_id', 'attendance_date'], 'unique_attendance_per_day');
            
            // Indexes for better query performance
            $table->index('owner_id');
            $table->index('employee_id');
            $table->index('attendance_date');
            $table->index(['month', 'year']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_attendance');
    }
};