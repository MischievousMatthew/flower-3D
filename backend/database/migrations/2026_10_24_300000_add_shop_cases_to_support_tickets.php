<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer complaints and service issues use the existing support tickets
 * instead of a second ticket system. A ticket with vendor_id belongs to that
 * shop: its staff work it (assignee, priority, linked order and refund),
 * while admins still see every ticket.
 *
 * Messages gain from_shop (a reply from the shop, signed with author_name)
 * and is_internal (a staff-only note the customer never sees).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('requester_role')->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('assigned_employee_id')->nullable()->after('vendor_id');
                $table->unsignedBigInteger('order_request_id')->nullable()->after('order_id');
                $table->boolean('recorded_by_shop')->default(false)->after('order_request_id');
                $table->index(['vendor_id', 'status', 'last_activity_at']);
            }
        });

        Schema::table('support_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('support_messages', 'from_shop')) {
                $table->boolean('from_shop')->default(false)->after('from_admin');
                $table->boolean('is_internal')->default(false)->after('from_shop');
                $table->string('author_name', 120)->nullable()->after('is_internal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            if (Schema::hasColumn('support_messages', 'from_shop')) {
                $table->dropColumn(['from_shop', 'is_internal', 'author_name']);
            }
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('support_tickets', 'vendor_id')) {
                $table->dropIndex(['vendor_id', 'status', 'last_activity_at']);
                $table->dropConstrainedForeignId('vendor_id');
                $table->dropColumn(['assigned_employee_id', 'order_request_id', 'recorded_by_shop']);
            }
        });
    }
};
