<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');

            // Basic Information
            $table->string('product_name');
            $table->text('description');
            $table->string('sku')->unique();
            $table->string('category');
            $table->string('flower_type');
            $table->string('color');
            $table->string('color_other')->nullable();

            $table->decimal('purchase_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->boolean('has_discount')->default(false);
            $table->decimal('discount_price', 10, 2)->nullable();

            $table->integer('quantity_in_stock')->default(0);
            $table->integer('min_stock_level')->default(0);
            $table->integer('max_stock_level')->nullable();
            $table->string('storage_location')->nullable();

            $table->enum('selling_type', ['per_piece', 'per_piece_customizable', 'bouquet'])->default('per_piece');

            $table->date('harvest_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->integer('freshness_days')->nullable();
            $table->enum('season', ['all-year', 'spring', 'summer', 'autumn', 'winter'])->default('all-year');

            $table->string('supplier_name')->nullable();
            $table->string('supplier_contact')->nullable();
            $table->string('supplier_sku')->nullable();
            $table->integer('supplier_lead_time')->nullable();

            $table->text('care_instructions')->nullable();

            // JSON column for occasions
            $table->json('occasion_tags')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_fragile')->default(false);
            $table->boolean('requires_refrigeration')->default(false);

            $table->enum('status', ['draft', 'active', 'inactive', 'discontinued'])->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index('category');
            $table->index('status');
            $table->index('flower_type');
        });

        // Optional: if migrating from old longtext column
        if (Schema::hasColumn('products', 'occasion_tags')) {
            $products = DB::table('products')->select('id', 'occasion_tags')->get();

            foreach ($products as $product) {
                if ($product->occasion_tags) {
                    $tags = array_filter(array_map('trim', explode(',', $product->occasion_tags)));
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['occasion_tags' => json_encode($tags)]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
