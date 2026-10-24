<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash on delivery after the order is handed over: when the shop received the
 * cash (from the rider or whoever collected it) and how much, then when that
 * cash was settled (deposited or counted into the books) and under what reference.
 * One row per order.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cod_collections')) {
            return;
        }

        Schema::create('cod_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('collected_amount', 12, 2);
            $table->string('collected_by', 120)->nullable();
            $table->date('collected_on');
            $table->string('recorded_by', 120)->nullable();
            $table->date('settled_on')->nullable();
            $table->string('settlement_reference', 80)->nullable();
            $table->string('settled_by', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'settled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_collections');
    }
};
