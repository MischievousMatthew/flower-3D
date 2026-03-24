<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();

            $table->string('delivery_id', 30)->unique();

            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            $table->enum('status', [
                'pending',
                'to_processed',
                'to_ship',
                'to_received',
                'completed',
                'returned',
                'refunded',
            ])->default('pending');

            // Points to employees, not users
            $table->unsignedBigInteger('last_scanned_by')->nullable();
            $table->foreign('last_scanned_by')->references('id')->on('employees')->onDelete('set null');
            $table->timestamp('last_scanned_at')->nullable();

            $table->string('barcode', 100)->unique()->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};