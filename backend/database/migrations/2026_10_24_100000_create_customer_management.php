<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer management, both ways:
 * - customer_preferences: what a customer says about themselves, whether the
 *   shops they buy from may see it, and whether they want offers from them
 * - customer_dates: birthdays, anniversaries and other days, added by the
 *   customer (vendor_id null) or recorded by a shop (vendor_id set)
 * - vendor_customer_profiles: what one shop has noted a customer likes
 * - customer_notes: a shop's internal notes, never shown to the customer
 * - favorite_products: products a customer hearted
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->json('flowers')->nullable();
            $table->json('colors')->nullable();
            $table->json('avoid')->nullable();
            $table->json('occasions')->nullable();
            $table->string('note', 500)->nullable();
            $table->boolean('share_with_shops')->default(false);
            // Offers from shops they bought from: off until they say yes; some shops can be muted
            $table->boolean('accept_offers')->default(false);
            $table->json('muted_offer_vendor_ids')->nullable();
            // A record of the yes: when and where (checkout, prompt or profile); and when we last asked
            $table->timestamp('offers_consent_at')->nullable();
            $table->string('offers_consent_source', 20)->nullable();
            $table->timestamp('offers_asked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('kind', 20); // birthday, anniversary, other
            $table->string('label', 80);
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('added_by', 120)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'vendor_id']);
            $table->index(['vendor_id', 'month', 'day']);
        });

        Schema::create('vendor_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->json('flowers')->nullable();
            $table->json('colors')->nullable();
            $table->json('avoid')->nullable();
            $table->json('occasions')->nullable();
            $table->json('favorite_product_ids')->nullable();
            $table->string('updated_by', 120)->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'customer_id']);
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->string('author_type', 20); // vendor, employee
            $table->unsignedBigInteger('author_id');
            $table->string('author_name', 120);
            $table->timestamps();

            $table->index(['vendor_id', 'customer_id', 'created_at']);
        });

        Schema::create('favorite_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_products');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('vendor_customer_profiles');
        Schema::dropIfExists('customer_dates');
        Schema::dropIfExists('customer_preferences');
    }
};
