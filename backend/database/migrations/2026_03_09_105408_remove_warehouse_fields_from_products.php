<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove warehouse-specific fields from the products table.
 *
 * These fields are moved to warehouse_batches, which supports
 * per-batch tracking (multiple arrivals of the same flower with
 * different harvest dates, expiry windows, and storage locations).
 *
 * Fields removed:
 *   - storage_location   → warehouse_batches.storage_location
 *   - harvest_date       → warehouse_batches.harvest_date
 *   - expiration_date    → warehouse_batches.expiration_date
 *   - freshness_days     → warehouse_batches.freshness_days (per-batch shelf life)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'storage_location',
                'harvest_date',
                'expiration_date',
                'freshness_days',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('storage_location')->nullable()->after('requires_refrigeration');
            $table->date('harvest_date')->nullable()->after('storage_location');
            $table->date('expiration_date')->nullable()->after('harvest_date');
            $table->unsignedSmallInteger('freshness_days')->nullable()->after('expiration_date');
        });
    }
};