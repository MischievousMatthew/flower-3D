<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('owner_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('owner_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('owner_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
        });

        Schema::table('warehouse_locations', function (Blueprint $table) {
            $table->foreignId('owner_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('owner_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
        });

        Schema::table('warehouse_batches', function (Blueprint $table) {
            $table->foreignId('owner_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
        });

        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->foreignId('owner_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('owner_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        Schema::table('warehouse_locations', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        Schema::table('warehouse_batches', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
