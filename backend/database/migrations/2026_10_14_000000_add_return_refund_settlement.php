<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns a return/refund request into a case the customer and vendor settle:
 * offers and counter-offers, messages, the agreed outcome, the item coming
 * back, and the refund or replacement being sent and confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Status gains settled ("approved"), "completed" and "withdrawn"
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_requests DROP CONSTRAINT IF EXISTS order_requests_status_check');
            DB::statement('ALTER TABLE order_requests ALTER COLUMN status TYPE varchar(20)');
        }

        Schema::table('order_requests', function (Blueprint $table) {
            $table->string('reason_category', 40)->nullable()->after('reason');
            // What the customer asked for: full_refund | partial_refund | replacement
            $table->string('resolution_requested', 20)->nullable()->after('reason_category');
            $table->decimal('amount_requested', 12, 2)->nullable()->after('resolution_requested');

            // The offer on the table, from either side
            $table->string('offer_resolution', 20)->nullable();
            $table->decimal('offer_amount', 12, 2)->nullable();
            $table->string('offer_by', 10)->nullable();
            $table->timestamp('offered_at')->nullable();

            // What both sides agreed
            $table->string('settled_resolution', 20)->nullable();
            $table->decimal('settled_amount', 12, 2)->nullable();
            $table->timestamp('settled_at')->nullable();

            // Carrying it out
            $table->timestamp('item_received_at')->nullable();
            $table->string('refund_method', 30)->nullable();
            $table->string('refund_reference', 120)->nullable();
            $table->timestamp('refund_sent_at')->nullable();
            $table->string('replacement_note', 255)->nullable();
            $table->timestamp('replacement_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('closed_reason', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
        });

        Schema::create('order_request_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_request_id')->constrained('order_requests')->cascadeOnDelete();
            $table->string('author_role', 10); // customer | vendor | system
            $table->string('author_name', 120)->nullable();
            $table->unsignedBigInteger('author_user_id')->nullable();
            $table->unsignedBigInteger('author_employee_id')->nullable();
            // message | opened | offer | accepted | rejected | withdrawn | item_received
            // | refund_sent | refund_confirmed | refund_missing | replacement_sent
            // | replacement_confirmed | replacement_missing
            $table->string('kind', 30)->default('message');
            $table->text('body')->nullable();
            $table->string('resolution', 20)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_type', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_request_id', 'created_at']);
        });

        // Existing requests: what was asked, and the old one-step approvals count as finished
        DB::table('order_requests')->whereNull('resolution_requested')->update([
            'resolution_requested' => 'full_refund',
            'last_activity_at' => DB::raw('updated_at'),
        ]);
        DB::table('order_requests')->where('status', 'pending')->update([
            'offer_resolution' => 'full_refund',
            'offer_by' => 'customer',
            'offered_at' => DB::raw('created_at'),
        ]);
        DB::table('order_requests')->where('status', 'approved')->update([
            'status' => 'completed',
            'settled_resolution' => 'full_refund',
            'settled_at' => DB::raw('updated_at'),
            'completed_at' => DB::raw('updated_at'),
        ]);
        DB::table('order_requests')->where('status', 'rejected')->whereNotNull('admin_notes')->update([
            'closed_reason' => DB::raw('admin_notes'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_request_messages');
        DB::table('order_requests')->where('status', 'completed')->update(['status' => 'approved']);
        DB::table('order_requests')->whereIn('status', ['withdrawn'])->update(['status' => 'rejected']);
        Schema::table('order_requests', function (Blueprint $table) {
            $table->dropColumn([
                'reason_category', 'resolution_requested', 'amount_requested',
                'offer_resolution', 'offer_amount', 'offer_by', 'offered_at',
                'settled_resolution', 'settled_amount', 'settled_at',
                'item_received_at', 'refund_method', 'refund_reference', 'refund_sent_at',
                'replacement_note', 'replacement_sent_at', 'completed_at', 'closed_reason', 'last_activity_at',
            ]);
        });
    }
};
