<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('cascade');

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->onDelete('cascade');

            // 1–5 stars
            $table->tinyInteger('rating')->unsigned();

            $table->text('comment')->nullable();

            // stored under storage/app/public/reviews/
            $table->string('image_path')->nullable();

            $table->boolean('is_anonymous')->default(false);

            $table->timestamps();

            // One review per product per order
            $table->unique(['product_id', 'user_id', 'order_id'], 'unique_review_per_purchase');

            $table->index('product_id');
            $table->index(['product_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};