<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift planning: named shifts (e.g. "Morning counter, 8–5, needs 2") that people are linked to,
 * and replacements (Jane covers John's shift). Assignments stay in staff_shifts, one per person
 * per day, so attendance and the vendor Schedules page keep reading the same rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_templates')) {
            Schema::create('shift_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 60);
                $table->time('start_time');
                $table->time('end_time');
                $table->string('color', 7)->default('#48bb78');
                $table->unsignedSmallInteger('headcount')->default(1);
                $table->json('days'); // weekday names the shift runs on
                $table->string('note', 255)->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->index(['owner_id', 'archived_at']);
            });
        }

        Schema::table('staff_shifts', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_shifts', 'shift_template_id')) {
                $table->foreignId('shift_template_id')->nullable()->constrained('shift_templates')->nullOnDelete();
                $table->string('status', 20)->default('scheduled'); // scheduled | replaced
                $table->string('replaced_reason', 20)->nullable(); // no_show | sick | swap | other
                $table->string('replaced_note', 255)->nullable();
                $table->unsignedBigInteger('replaced_by_employee_id')->nullable();
                $table->unsignedBigInteger('replaces_shift_id')->nullable();
                $table->string('assigned_by', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table) {
            if (Schema::hasColumn('staff_shifts', 'shift_template_id')) {
                $table->dropConstrainedForeignId('shift_template_id');
                $table->dropColumn(['status', 'replaced_reason', 'replaced_note', 'replaced_by_employee_id', 'replaces_shift_id', 'assigned_by']);
            }
        });
        Schema::dropIfExists('shift_templates');
    }
};
