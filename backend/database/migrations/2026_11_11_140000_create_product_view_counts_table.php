<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many times each product page was opened, per day, for the POPULAR tag.
 * One number per product per day: nothing about who looked.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_view_counts')) {
            return;
        }
        Schema::create('product_view_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->unique(['product_id', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_view_counts');
    }
};
