<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance & business management for vendors:
 *  - shop operating expenses the vendor records (rent, utilities, packaging…);
 *  - what each sold item cost the shop, so profit & loss uses the cost at the time of sale.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->date('spent_on');
            $table->string('payment_method', 20)->nullable();
            $table->string('payee', 120)->nullable();
            $table->string('reference', 80)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_type', 10)->nullable();
            $table->text('notes')->nullable();
            $table->string('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'spent_on']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
        Schema::dropIfExists('vendor_expenses');
    }
};
