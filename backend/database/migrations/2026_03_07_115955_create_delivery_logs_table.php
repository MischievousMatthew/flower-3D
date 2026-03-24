<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('delivery_id');
            $table->foreign('delivery_id')->references('id')->on('deliveries')->onDelete('cascade');

            $table->enum('status', [
                'pending',
                'to_processed',
                'to_ship',
                'to_received',
                'completed',
                'returned',
                'refunded',
            ]);

            // Points to employees, not users
            $table->unsignedBigInteger('scanned_by')->nullable();
            $table->foreign('scanned_by')->references('id')->on('employees')->onDelete('set null');
            $table->timestamp('scanned_at');

            $table->string('notes', 500)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('delivery_id');
            $table->index('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_logs');
    }
};