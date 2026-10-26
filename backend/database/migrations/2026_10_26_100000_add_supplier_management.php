<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier management: what kind of supplier it is, when it delivers and how
 * long it takes, what it sells and for how much, and how the shop rates it.
 * Only adds; existing suppliers keep working with the new fields empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $t) {
            if (! Schema::hasColumn('suppliers', 'supplier_type')) $t->string('supplier_type', 30)->nullable();
            if (! Schema::hasColumn('suppliers', 'delivery_days')) $t->json('delivery_days')->nullable();
            if (! Schema::hasColumn('suppliers', 'lead_time_days')) $t->unsignedSmallInteger('lead_time_days')->nullable();
            if (! Schema::hasColumn('suppliers', 'order_cutoff')) $t->string('order_cutoff', 5)->nullable();
            if (! Schema::hasColumn('suppliers', 'minimum_order')) $t->decimal('minimum_order', 12, 2)->nullable();
            if (! Schema::hasColumn('suppliers', 'payment_terms')) $t->string('payment_terms', 60)->nullable();
            if (! Schema::hasColumn('suppliers', 'notes')) $t->text('notes')->nullable();
        });

        if (! Schema::hasTable('supplier_products')) {
            Schema::create('supplier_products', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('owner_id')->index();
                $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $t->string('name', 160);
                $t->string('category', 40)->nullable();
                $t->string('unit', 20)->default('stem');
                $t->decimal('price', 12, 2)->default(0);
                $t->unsignedInteger('min_qty')->nullable();
                $t->unsignedBigInteger('product_id')->nullable();
                $t->boolean('is_available')->default(true);
                $t->string('notes', 500)->nullable();
                $t->timestamp('price_updated_at')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_ratings')) {
            Schema::create('supplier_ratings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('owner_id')->index();
                $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $t->unsignedBigInteger('purchase_order_id')->nullable();
                $t->unsignedTinyInteger('quality');
                $t->unsignedTinyInteger('timeliness');
                $t->unsignedTinyInteger('communication');
                $t->text('comment')->nullable();
                $t->string('rated_by_type', 20)->nullable();
                $t->unsignedBigInteger('rated_by_id')->nullable();
                $t->string('rated_by_name', 160)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ratings');
        Schema::dropIfExists('supplier_products');
        Schema::table('suppliers', function (Blueprint $t) {
            foreach (['supplier_type', 'delivery_days', 'lead_time_days', 'order_cutoff', 'minimum_order', 'payment_terms', 'notes'] as $c) {
                if (Schema::hasColumn('suppliers', $c)) $t->dropColumn($c);
            }
        });
    }
};
