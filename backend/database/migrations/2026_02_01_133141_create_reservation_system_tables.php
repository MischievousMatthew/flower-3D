<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add reservation_date to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->date('reservation_date')->nullable()->after('delivery_type');
            $table->index('reservation_date');
            
            // Remove tax_amount (no longer needed)
            $table->dropColumn('tax_amount');
            
            // Add 3D model reference
            $table->string('model_3d_reference')->nullable()->after('customer_notes');
        });

        // 2. Add 3d_model_path to order_items
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('model_3d_path')->nullable()->after('product_image');
            $table->string('model_3d_url')->nullable()->after('model_3d_path');
        });

        // 3. Create vendor_closed_dates table
        Schema::create('vendor_closed_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->date('closed_date');
            $table->string('reason')->nullable();
            $table->enum('type', ['manual', 'holiday', 'emergency'])->default('manual');
            $table->timestamps();
            
            $table->unique(['vendor_id', 'closed_date']);
            $table->index(['vendor_id', 'closed_date']);
        });

        // 4. Create reservation_availability_cache table (for performance)
        Schema::create('reservation_availability_caches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->integer('orders_count')->default(0);
            $table->integer('max_orders')->default(0);
            $table->enum('status', ['available', 'almost_full', 'fully_booked', 'closed'])->default('available');
            $table->timestamps();
            
            $table->unique(['vendor_id', 'date']);
            $table->index(['vendor_id', 'date', 'status']);
        });

        // 5. Update vendor_applications to ensure max_orders_per_day exists
        if (!Schema::hasColumn('vendor_applications', 'max_orders_per_day')) {
            Schema::table('vendor_applications', function (Blueprint $table) {
                $table->integer('max_orders_per_day')->default(10)->after('delivery_handled_by');
            });
        }
        
        // 6. Add default_delivery_fee to vendor_applications
        if (!Schema::hasColumn('vendor_applications', 'default_delivery_fee')) {
            Schema::table('vendor_applications', function (Blueprint $table) {
                $table->decimal('default_delivery_fee', 10, 2)->default(50.00)->after('max_orders_per_day');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['reservation_date', 'model_3d_reference']);
            $table->decimal('tax_amount', 10, 2)->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['model_3d_path', 'model_3d_url']);
        });

        Schema::dropIfExists('vendor_closed_dates');
        Schema::dropIfExists('reservation_availability_cache');
        
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropColumn(['default_delivery_fee']);
        });
    }
};