<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouse batches — one row per physical batch of flowers received.
 *
 * This replaces the single harvest_date / expiration_date / storage_location
 * fields that were on the products table. A product can now have many batches,
 * each with its own dates, location, quantity, and condition status.
 *
 * Lifecycle example for 50 roses:
 *   Batch created (condition: fresh)  →  moved to Cooler A
 *   3 days later condition updated    →  aging
 *   5 days later partially culled     →  qty_remaining reduced, batch_log entry added
 *   Fully depleted                    →  status set to depleted
 *
 * Barcode:
 *   Each batch gets a unique barcode (auto-generated or assigned on arrival).
 *   Scanning the barcode in the warehouse UI pulls up this batch directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_batches', function (Blueprint $table) {
            $table->id();

            // ── Product reference (inventory) ─────────────────────────────
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();

            // ── Location reference ────────────────────────────────────────
            $table->foreignId('warehouse_location_id')
                  ->nullable()
                  ->constrained('warehouse_locations')
                  ->nullOnDelete();
            // Denormalized for fast queries / display without join
            $table->string('storage_location')->nullable();

            // ── Batch identity ────────────────────────────────────────────
            $table->string('batch_number')->unique();        // e.g. "ROSE-2024-001"
            $table->string('barcode')->unique()->nullable(); // scanned barcode
            $table->string('lot_number')->nullable();        // supplier's lot / PO reference

            // ── Dates ─────────────────────────────────────────────────────
            $table->date('received_date');                   // when this batch arrived
            $table->date('harvest_date')->nullable();        // when flowers were cut
            $table->date('expiration_date')->nullable();     // computed or supplier-provided
            $table->unsignedSmallInteger('freshness_days')->nullable(); // expected shelf life for this batch

            // ── Quantity ──────────────────────────────────────────────────
            $table->unsignedInteger('qty_received');         // how many arrived
            $table->unsignedInteger('qty_remaining');        // current physical count

            // ── Condition ─────────────────────────────────────────────────
            // fresh → aging → wilting → spoiled → discarded
            $table->enum('condition_status', [
                'fresh',
                'aging',
                'wilting',
                'spoiled',
                'discarded',
            ])->default('fresh');

            // ── Status ────────────────────────────────────────────────────
            // active = available in warehouse
            // depleted = all units used/shipped/culled
            // quarantine = held pending inspection
            $table->enum('status', ['active', 'depleted', 'quarantine'])
                  ->default('active');

            // ── Notes ────────────────────────────────────────────────────
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['product_id', 'status']);
            $table->index(['expiration_date', 'status']);
            $table->index(['condition_status', 'status']);
            $table->index('warehouse_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_batches');
    }
};