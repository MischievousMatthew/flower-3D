<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop & product management for vendors:
 *  - a product's own discount can be scheduled (start / end);
 *  - every manual stock change is kept with its reason;
 *  - seasonal promotions take a percentage or amount off a set of products for a while.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('discount_starts_at')->nullable()->after('discount_price');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_starts_at');
        });

        Schema::create('product_stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('reason', 30);
            $table->string('note', 500)->nullable();
            $table->string('actor_name')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['owner_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('vendor_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('discount_type', 10);          // percent | fixed
            $table->decimal('discount_value', 10, 2);
            $table->string('applies_to', 12);             // all | category | occasion | products
            $table->json('targets')->nullable();          // category values, occasions or product ids
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['owner_id', 'is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_promotions');
        Schema::dropIfExists('product_stock_adjustments');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['discount_starts_at', 'discount_ends_at']);
        });
    }
};
