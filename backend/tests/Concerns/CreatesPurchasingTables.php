<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers, purchase orders, items, shipments, warehouse batches and funding
 * requests, plus the purchase order and receiving migrations, for tests.
 * Needs CreatesAttendanceTables, CreatesPayrollTables and CreatesFinanceTables first.
 */
trait CreatesPurchasingTables
{
    protected function createPurchasingTables(): void
    {
        foreach (['supplier_ratings', 'supplier_products', 'purchase_order_receipt_files', 'purchase_order_receipt_lines', 'purchase_order_receipts', 'warehouse_locations', 'shipments', 'purchase_order_events', 'warehouse_batches', 'purchase_order_items', 'purchase_orders', 'suppliers', 'funding_requests'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('product_name')->nullable();
            $t->string('sku')->nullable();
            $t->integer('quantity_in_stock')->default(0);
            $t->softDeletes();
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('company_name');
            $t->string('contact_person')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->string('status')->default('active');
            $t->string('logo')->nullable();
            $t->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->string('order_number')->nullable();
            $t->string('status')->default('pending');
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->timestamp('created_at')->nullable()->useCurrent();
        });
        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('purchase_order_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('product_name');
            $t->integer('quantity');
            $t->decimal('price', 12, 2);
            $t->decimal('subtotal', 15, 2)->storedAs('quantity * price');
        });
        Schema::create('warehouse_batches', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('warehouse_location_id')->nullable();
            $t->string('storage_location')->nullable();
            $t->string('batch_number')->nullable();
            $t->string('barcode')->nullable();
            $t->string('lot_number')->nullable();
            $t->date('received_date')->nullable();
            $t->date('harvest_date')->nullable();
            $t->date('expiration_date')->nullable();
            $t->unsignedSmallInteger('freshness_days')->nullable();
            $t->unsignedInteger('qty_received')->default(0);
            $t->unsignedInteger('qty_remaining')->default(0);
            $t->string('condition_status')->default('fresh');
            $t->string('status')->default('active');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('purchase_order_id');
            $t->string('tracking_number')->nullable();
            $t->string('carrier')->nullable();
            $t->date('shipped_date')->nullable();
            $t->date('received_date')->nullable();
            $t->string('status')->default('pending');
        });
        Schema::create('funding_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('linked_order_id')->nullable();
            $t->unsignedInteger('shelf_life')->nullable();
            $t->timestamps();
        });
        Schema::create('warehouse_locations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('warehouse_id')->nullable();
            $t->string('name');
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_26_200000_purchase_order_management.php'))->up();
        (require database_path('migrations/2026_10_26_100000_add_supplier_management.php'))->up();
        (require database_path('migrations/2026_10_26_300000_receiving_and_inspection.php'))->up();
    }
}
