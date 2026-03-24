<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // 'return' | 'refund'
            $table->enum('type', ['return', 'refund']);

            $table->text('reason');

            // Optional proof file stored in storage/app/public/returns
            $table->string('media_path')->nullable();

            // 'image' | 'video' | null
            $table->enum('media_type', ['image', 'video'])->nullable();

            // Workflow: pending → approved | rejected
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending');

            // Admin notes when approving / rejecting
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            // One active (pending) request per order per type
            $table->index(['order_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_requests');
    }
};