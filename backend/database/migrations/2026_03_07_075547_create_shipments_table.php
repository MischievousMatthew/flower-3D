<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->string('tracking_number')->unique();
            $table->string('carrier');
            $table->date('shipped_date')->nullable();
            $table->date('received_date')->nullable();
            $table->enum('status', [
                'pending',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed',
                'returned',
            ])->default('pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};