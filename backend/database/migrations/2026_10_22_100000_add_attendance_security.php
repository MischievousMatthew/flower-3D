<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance that's hard to cheat: shop location + approved kiosk devices, one-time
 * scan sessions, enrolled faces, signed QR codes, reviews and corrections.
 * See docs/superpowers/specs/2026-10-22-attendance-security-design.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_settings')) {
            Schema::create('attendance_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedSmallInteger('radius_m')->default(100);
                $table->unsignedSmallInteger('late_grace_minutes')->default(10);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_devices')) {
            Schema::create('attendance_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('token_hash', 64)->unique();
                $table->string('status', 20)->default('pending'); // pending | approved | revoked
                $table->string('requested_by', 150)->nullable();
                $table->unsignedBigInteger('requested_by_employee_id')->nullable();
                $table->decimal('request_latitude', 10, 7)->nullable();
                $table->decimal('request_longitude', 10, 7)->nullable();
                $table->unsignedInteger('request_distance_m')->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index(['owner_id', 'status']);
            });
        }

        if (! Schema::hasTable('attendance_sessions')) {
            Schema::create('attendance_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('device_id')->constrained('attendance_devices')->cascadeOnDelete();
                $table->foreignId('employee_info_id')->constrained('employees_info')->cascadeOnDelete();
                $table->string('purpose', 20); // time_in | time_out | enroll
                $table->json('challenges');
                $table->json('flash');
                $table->json('flags')->nullable();
                $table->unsignedBigInteger('operator_employee_id')->nullable();
                $table->string('operator_name', 150)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->index(['owner_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('face_enrollments')) {
            Schema::create('face_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('employee_info_id')->unique()->constrained('employees_info')->cascadeOnDelete();
                $table->json('descriptors');
                $table->json('photos')->nullable();
                $table->string('enrolled_by', 150)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_corrections')) {
            Schema::create('attendance_corrections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('employee_info_id')->constrained('employees_info')->cascadeOnDelete();
                $table->unsignedBigInteger('attendance_id')->nullable();
                $table->date('date');
                $table->time('time_in');
                $table->time('time_out')->nullable();
                $table->string('reason', 500);
                $table->string('requested_by', 150)->nullable();
                $table->unsignedBigInteger('requested_by_employee_id')->nullable();
                $table->string('status', 20)->default('pending'); // pending | approved | rejected
                $table->string('reviewed_by', 150)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('review_note', 500)->nullable();
                $table->timestamps();

                $table->index(['owner_id', 'status']);
            });
        }

        Schema::table('employee_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_attendance', 'review_status')) {
                $table->string('review_status', 20)->nullable()->index(); // null = fine | pending | approved | rejected
                $table->json('review_reasons')->nullable();
                $table->string('reviewed_by', 150)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('review_note', 500)->nullable();
                $table->json('time_in_evidence')->nullable();
                $table->json('time_out_evidence')->nullable();
                $table->string('source', 20)->nullable(); // kiosk | owner | correction | legacy
            }
        });

        Schema::table('employees_info', function (Blueprint $table) {
            if (! Schema::hasColumn('employees_info', 'qr_version')) {
                $table->unsignedInteger('qr_version')->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees_info', function (Blueprint $table) {
            if (Schema::hasColumn('employees_info', 'qr_version')) {
                $table->dropColumn('qr_version');
            }
        });
        Schema::table('employee_attendance', function (Blueprint $table) {
            if (Schema::hasColumn('employee_attendance', 'review_status')) {
                $table->dropIndex(['review_status']);
                $table->dropColumn(['review_status', 'review_reasons', 'reviewed_by', 'reviewed_at', 'review_note', 'time_in_evidence', 'time_out_evidence', 'source']);
            }
        });
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('face_enrollments');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('attendance_settings');
    }
};
