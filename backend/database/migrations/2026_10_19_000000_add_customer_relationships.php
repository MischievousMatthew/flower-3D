<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer relationships for vendors:
 *  - inquiries: questions customers ask about a product or one of their orders;
 *  - the shop's public reply to a product review;
 *  - vendor notifications (new orders, payments, low stock, messages…) made
 *    from events, with a key so each event notifies once;
 *  - which of those notifications each vendor wants.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('answered_by', 120)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->string('status', 12)->default('open'); // open, answered, closed
            // A product question with its answer can be shown to other shoppers
            $table->boolean('is_public')->default(true);
            $table->timestamp('customer_seen_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status', 'created_at']);
            $table->index(['product_id', 'status']);
            $table->index(['customer_id', 'created_at']);
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->text('vendor_reply')->nullable()->after('comment');
            $table->timestamp('vendor_replied_at')->nullable()->after('vendor_reply');
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('event_key', 120)->nullable()->after('type');
            $table->unique(['user_id', 'event_key']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->json('settings');
            $table->timestamp('synced_at')->nullable(); // last time events were turned into notifications
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'event_key']);
            $table->dropColumn('event_key');
        });
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn(['vendor_reply', 'vendor_replied_at']);
        });
        Schema::dropIfExists('customer_inquiries');
    }
};
