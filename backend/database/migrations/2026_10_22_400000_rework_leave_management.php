<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave & overtime management.
 *
 * 1. employee_leaves.owner_id held an HR employee's id, while payroll, attendance and the shift
 *    planner look leave up by the shop (users.id) — so approved leave was invisible to them.
 *    Existing rows move to the shop's id and the column now points at users.
 * 2. Leave can be cancelled, carry a supporting document, and be reviewed by the shop owner.
 * 3. Leave rules per shop (allowance, paid, document needed), balance adjustments,
 *    overtime requests, and an approval history for both.
 */
return new class extends Migration
{
    private const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver !== 'sqlite') {
            // Move each leave to its shop: the HR employee it pointed at → that employee's owner
            DB::statement('UPDATE employee_leaves SET owner_id = (SELECT e.owner_id FROM employees e WHERE e.id = employee_leaves.owner_id)
                WHERE EXISTS (SELECT 1 FROM employees e WHERE e.id = employee_leaves.owner_id AND e.owner_id IS NOT NULL)');

            Schema::table('employee_leaves', function (Blueprint $table) {
                $table->dropForeign(['owner_id']);
                $table->dropForeign(['reviewed_by']);
            });
            if ($driver === 'pgsql') {
                // NOT VALID: new rows are checked; any old row that couldn't be matched to a shop stays put
                DB::statement('ALTER TABLE employee_leaves ADD CONSTRAINT employee_leaves_owner_id_foreign
                    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE NOT VALID');
            } else {
                Schema::table('employee_leaves', function (Blueprint $table) {
                    $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
                });
            }

            $quoted = implode(', ', array_map(fn ($s) => "'{$s}'", self::STATUSES));
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE employee_leaves ALTER COLUMN status TYPE VARCHAR(20)');
                DB::statement('ALTER TABLE employee_leaves DROP CONSTRAINT IF EXISTS employee_leaves_status_check');
                DB::statement("ALTER TABLE employee_leaves ADD CONSTRAINT employee_leaves_status_check CHECK (status IN ({$quoted}))");
            } elseif ($driver === 'mysql') {
                DB::statement("ALTER TABLE employee_leaves MODIFY COLUMN status ENUM({$quoted}) NOT NULL DEFAULT 'pending'");
            }
        }

        Schema::table('employee_leaves', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_leaves', 'reviewer_name')) {
                $table->string('reviewer_name', 150)->nullable();
                $table->string('filed_by', 20)->default('employee'); // employee | hr | owner
                $table->string('filed_by_name', 150)->nullable();
                $table->boolean('badge_verified')->default(true);
                $table->string('document_public_id', 255)->nullable();
                $table->string('document_name', 255)->nullable();
                $table->string('document_format', 20)->nullable();
                $table->string('document_resource_type', 20)->nullable();
                $table->timestamp('cancelled_at')->nullable();
            }
        });

        if (! Schema::hasTable('leave_policies')) {
            Schema::create('leave_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('leave_type', 40);
                $table->decimal('annual_days', 6, 1)->nullable(); // null = no limit
                $table->boolean('is_paid')->default(true);
                $table->boolean('enabled')->default(true);
                $table->boolean('requires_document')->default(false);
                $table->timestamps();
                $table->unique(['owner_id', 'leave_type']);
            });
        }

        if (! Schema::hasTable('leave_balance_adjustments')) {
            Schema::create('leave_balance_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('employee_info_id')->constrained('employees_info')->cascadeOnDelete();
                $table->string('leave_type', 40);
                $table->unsignedSmallInteger('year');
                $table->decimal('days', 6, 1); // + adds days, − takes them away
                $table->string('note', 255);
                $table->string('adjusted_by', 150)->nullable();
                $table->timestamps();
                $table->index(['owner_id', 'employee_info_id', 'year']);
            });
        }

        if (! Schema::hasTable('overtime_requests')) {
            Schema::create('overtime_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('employee_info_id')->constrained('employees_info')->cascadeOnDelete();
                $table->date('date');
                $table->unsignedSmallInteger('minutes');
                $table->string('reason', 1000);
                $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
                $table->string('filed_by', 20)->default('employee');
                $table->string('filed_by_name', 150)->nullable();
                $table->string('reviewer_name', 150)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('review_note', 1000)->nullable();
                $table->timestamps();
                $table->index(['owner_id', 'status']);
                $table->index(['employee_info_id', 'date']);
            });
        }

        if (! Schema::hasTable('request_events')) {
            Schema::create('request_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('subject_type', 30); // leave | overtime_request
                $table->unsignedBigInteger('subject_id');
                $table->string('action', 30); // filed | approved | rejected | cancelled | document | balance
                $table->string('actor_name', 150)->nullable();
                $table->string('note', 1000)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['subject_type', 'subject_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('request_events');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('leave_balance_adjustments');
        Schema::dropIfExists('leave_policies');
        Schema::table('employee_leaves', function (Blueprint $table) {
            if (Schema::hasColumn('employee_leaves', 'reviewer_name')) {
                $table->dropColumn(['reviewer_name', 'filed_by', 'filed_by_name', 'badge_verified', 'document_public_id',
                    'document_name', 'document_format', 'document_resource_type', 'cancelled_at']);
            }
        });
        // owner_id stays on the shop: moving it back to an HR employee would bring the bug back
    }
};
