<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_applications', function (Blueprint $table) {
            $table->id();
            
            // Application tracking
            $table->string('application_id')->unique();
            $table->string('status_token')->nullable()->unique();
            
            // Store Information
            $table->string('store_name');
            $table->text('store_description');
            $table->enum('business_type', ['individual', 'partnership', 'corporation']);
            $table->text('store_address');
            $table->string('service_areas');
            $table->string('operating_hours');
            
            // Owner Information & Verification
            $table->string('owner_name');
            $table->string('position');
            $table->string('contact_number');
            $table->string('email');
            $table->string('government_id_number'); // NEW FIELD
            $table->string('government_id_path');
            $table->string('selfie_with_id_path');
            $table->string('proof_of_address_path');
            
            // Business Registration (Optional with ID numbers)
            $table->string('dti_number')->nullable();
            $table->string('sec_number')->nullable();
            $table->string('barangay_clearance_number')->nullable(); // NEW FIELD
            $table->string('barangay_clearance_path')->nullable();
            $table->string('mayor_permit_number')->nullable(); // NEW FIELD
            $table->string('mayor_permit_path')->nullable();
            $table->string('bir_tin')->nullable();
            
            // Payment & Payout Details - These fields will be filled after login
            $table->enum('payout_method', ['bank', 'gcash', 'maya'])->nullable();
            $table->string('account_holder_name')->nullable();
            $table->text('account_number')->nullable(); // Encrypted
            $table->string('bank_name')->nullable();
            $table->text('billing_address')->nullable();
            
            // Products & Services - Will be filled after login
            $table->json('product_types')->nullable();
            $table->string('price_range')->nullable();
            $table->boolean('same_day_delivery')->nullable();
            $table->string('order_cutoff')->nullable();
            
            // Delivery & Operations - Will be filled after login
            $table->enum('delivery_handled_by', ['vendor', 'platform'])->nullable();
            $table->string('delivery_fee')->nullable();
            $table->integer('max_orders_per_day')->nullable();
            $table->string('lead_time')->nullable();
            $table->text('cancellation_policy')->nullable();
            
            // Optional Information
            $table->string('store_logo_path')->nullable();
            $table->json('portfolio_photos_paths')->nullable();
            $table->string('facebook_page')->nullable();
            $table->string('instagram_page')->nullable();
            
            // Application Status & Tracking
            $table->enum('status', ['pending', 'approved', 'rejected', 'under_review'])->default('pending');
            $table->enum('verification_level', ['basic', 'verified', 'premium'])->default('basic');
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            
            // Profile completion tracking
            $table->boolean('payment_details_completed')->default(false); // NEW FIELD
            $table->boolean('product_details_completed')->default(false); // NEW FIELD
            $table->boolean('delivery_details_completed')->default(false); // NEW FIELD
            $table->boolean('profile_fully_completed')->default(false); // NEW FIELD
            
            $table->timestamps();
            
            // Indexes
            $table->index('email');
            $table->index('status');
            $table->index('application_id');
            $table->index('submitted_at');
            $table->index('verification_level');
            $table->index(['status', 'submitted_at']);
            $table->index('profile_fully_completed');
        });
        
        // Add full-text search for store name and description
        DB::statement('ALTER TABLE vendor_applications ADD FULLTEXT fulltext_store (store_name, store_description)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_applications');
    }
};