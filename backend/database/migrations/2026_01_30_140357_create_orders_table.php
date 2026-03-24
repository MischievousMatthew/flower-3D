<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            
            // Order Details
            $table->string('status')->default('pending'); // pending, processing, completed, cancelled, failed
            $table->string('payment_status')->default('unpaid'); // unpaid, paid, refunded, failed
            $table->string('payment_method'); // cod, gcash, maya, card
            
            // Amounts
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            
            // Delivery Information
            $table->string('delivery_type')->nullable(); // standard, express, same-day
            $table->text('delivery_address');
            $table->string('delivery_contact_name');
            $table->string('delivery_contact_number');
            
            // Payment Information (PayMongo)
            $table->string('paymongo_payment_intent_id')->nullable();
            $table->string('paymongo_source_id')->nullable();
            $table->string('paymongo_checkout_url')->nullable();
            $table->text('paymongo_response')->nullable();
            
            // Store Information
            $table->string('store_name');
            $table->text('store_address')->nullable();
            
            // Special Instructions
            $table->text('customer_notes')->nullable();
            
            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('order_number');
            $table->index('user_id');
            $table->index('vendor_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};