<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_time')->nullable();
            $table->unsignedInteger('unread_count_customer')->default(0);
            $table->unsignedInteger('unread_count_vendor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['vendor_id', 'customer_id']);
            
            $table->index(['vendor_id', 'last_message_time']);
            $table->index(['customer_id', 'last_message_time']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};