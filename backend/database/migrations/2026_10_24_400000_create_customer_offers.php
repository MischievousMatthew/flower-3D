<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offers a shop sends to a customer segment (new, repeat, frequent, events,
 * inactive). Only customers who said yes to offers, and haven't muted the
 * shop, receive them; each recipient row is what they see on their side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->string('segment', 30);
            $table->string('title', 120);
            $table->text('body');
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->string('sent_by', 120)->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at']);
        });

        Schema::create('customer_offer_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('customer_offers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id');
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['vendor_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_offer_recipients');
        Schema::dropIfExists('customer_offers');
    }
};
