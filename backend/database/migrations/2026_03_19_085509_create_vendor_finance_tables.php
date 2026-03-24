<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── vendor_balances ──────────────────────────────────────────────────
        // One row per vendor. Tracks running balance + lifetime totals.
        Schema::create('vendor_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->decimal('balance', 12, 2)->default(0);          // current available balance
            $table->decimal('total_earned', 12, 2)->default(0);      // lifetime credits
            $table->decimal('total_withdrawn', 12, 2)->default(0);   // lifetime debits

            $table->timestamps();
        });

        // ── vendor_transactions ──────────────────────────────────────────────
        // Immutable ledger — one row per financial event.
        Schema::create('vendor_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            // credit = money coming in, debit = money going out
            $table->enum('type', ['credit', 'debit']);

            // what kind of event triggered this entry
            $table->enum('category', [
                'order_revenue',   // order completed → vendor earns
                'refund',          // customer refund → vendor loses
                'adjustment',      // manual correction
            ]);

            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);

            $table->string('description');

            $table->enum('status', ['pending', 'completed', 'failed'])
                ->default('completed');

            $table->json('metadata')->nullable(); // store order_number, items count etc.

            $table->timestamps();

            // fast lookups for the financial dashboard
            $table->index(['vendor_id', 'created_at']);
            $table->index(['vendor_id', 'type']);
            $table->index(['vendor_id', 'category']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_transactions');
        Schema::dropIfExists('vendor_balances');
    }
};