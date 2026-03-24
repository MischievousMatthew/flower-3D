<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouse locations — named physical zones inside the warehouse.
 *
 * Examples: "Cooler A", "Cooler B", "Shelf Row 3", "Staging Area"
 *
 * Each location can specify:
 *   - Whether it is refrigerated (enforced when placing fragile/cold flowers)
 *   - Capacity (max units that fit)
 *   - A barcode for scanning during physical operations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();

            $table->string('name');                          // e.g. "Cooler A"
            $table->string('code')->unique();                // e.g. "COOL-A" — used in barcodes
            $table->text('description')->nullable();
            $table->string('zone')->nullable();              // optional grouping: "Cold Storage", "Dry"
            $table->boolean('is_refrigerated')->default(false);
            $table->unsignedInteger('capacity_units')->nullable(); // max units this location can hold
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
    }
};