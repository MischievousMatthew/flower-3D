<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A funding request can now cover several products (one line each), and is
 * paid by bank transfer or e-wallet instead of the old credit terms.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funding_request_items')) {
            Schema::create('funding_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('funding_request_id')->constrained('funding_requests')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('product_name');
                $table->string('sku')->nullable();
                $table->string('uom', 20)->nullable();
                $table->integer('current_stock')->nullable();
                $table->integer('quantity');
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->decimal('line_total', 12, 2)->default(0);
                $table->timestamps();

                $table->index('funding_request_id');
            });
        }

        Schema::table('funding_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('funding_requests', 'payment_method')) {
                $table->string('payment_method', 20)->nullable()->after('payment_terms');
            }
            if (! Schema::hasColumn('funding_requests', 'payment_channel')) {
                $table->string('payment_channel', 60)->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_request_items');

        Schema::table('funding_requests', function (Blueprint $table) {
            foreach (['payment_channel', 'payment_method'] as $col) {
                if (Schema::hasColumn('funding_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
