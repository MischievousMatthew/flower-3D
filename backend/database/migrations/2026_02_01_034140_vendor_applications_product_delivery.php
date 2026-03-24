<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            // Remove old price_range field
            $table->dropColumn('price_range');
            
            // Remove delivery_fee field (will be dynamic per order)
            $table->dropColumn('delivery_fee');
            
            // Remove order_cutoff field (replaced with cutoff_times)
            $table->dropColumn('order_cutoff');
            
            // Add new price fields
            $table->decimal('price_min', 10, 2)->nullable()->after('product_types');
            $table->decimal('price_max', 10, 2)->nullable()->after('price_min');
            
            // Add cutoff_times JSON field
            $table->json('cutoff_times')->nullable()->after('same_day_delivery')
                ->comment('Array of {day: "Monday", time: "21:00"}');
            
            // Update delivery_handled_by to only allow 'self'
            // Note: Existing enum constraint will need manual update
            $table->string('delivery_handled_by')->nullable()->change();
        });
        
        // Update existing records to use 'self' for delivery
        DB::table('vendor_applications')
            ->whereNotNull('delivery_handled_by')
            ->update(['delivery_handled_by' => 'self']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            // Restore old fields
            $table->string('price_range')->nullable();
            $table->string('delivery_fee')->nullable();
            $table->string('order_cutoff')->nullable();
            
            // Remove new fields
            $table->dropColumn(['price_min', 'price_max', 'cutoff_times']);
        });
    }
};