<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouse batch logs — append-only event history for each batch.
 *
 * Every significant event on a batch is recorded here:
 *   - CONDITION_UPDATED  — staff marked a batch as aging/wilting/spoiled
 *   - QUANTITY_ADJUSTED  — units added or removed (partial cull, receiving correction)
 *   - TRANSFERRED        — batch (or portion) moved to a different location
 *   - CULLED             — units discarded as spoiled / unsellable
 *   - SCANNED            — barcode scan event (audit trail)
 *   - NOTE               — free-text note added by staff
 *
 * This table is never updated — only inserted into.
 * It gives a full audit trail without touching the source batch row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_batch_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('warehouse_batch_id')
                  ->constrained('warehouse_batches')
                  ->cascadeOnDelete();

            // Who performed the action
            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->enum('event_type', [
                'CONDITION_UPDATED',
                'QUANTITY_ADJUSTED',
                'TRANSFERRED',
                'CULLED',
                'SCANNED',
                'NOTE',
            ]);

            // Snapshot of condition before and after
            $table->string('condition_before')->nullable();
            $table->string('condition_after')->nullable();

            // Quantity change (positive = added, negative = removed)
            $table->integer('qty_change')->nullable();
            $table->unsignedInteger('qty_after')->nullable(); // snapshot of qty_remaining after event

            // Location change
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();

            $table->text('notes')->nullable();

            // created_at only — logs are never updated
            $table->timestamp('created_at')->useCurrent();

            $table->index(['warehouse_batch_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_batch_logs');
    }
};