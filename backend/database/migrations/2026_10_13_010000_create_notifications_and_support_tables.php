<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An announcement an admin sent to vendors and/or customers
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 20); // vendors | customers | all
            $table->string('title', 140);
            $table->text('body');
            $table->string('link_url', 500)->nullable();
            $table->string('priority', 20)->default('normal'); // normal | important
            $table->unsignedInteger('recipients')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Each person's inbox (announcements, support replies, ...)
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('broadcast_id')->nullable()->constrained('broadcasts')->cascadeOnDelete();
            $table->string('type', 30); // announcement | support_reply | support_status
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->boolean('important')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
        });

        // Help requests from vendors and customers
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('requester_role', 20); // vendor | customer
            $table->string('subject', 160);
            $table->string('category', 30); // account | payments | orders | products | subscription | technical | other
            $table->string('priority', 20)->default('normal'); // low | normal | high
            $table->string('status', 20)->default('open')->index(); // open | waiting | resolved | closed
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('from_admin')->default(false);
            $table->text('body');
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('broadcasts');
    }
};
