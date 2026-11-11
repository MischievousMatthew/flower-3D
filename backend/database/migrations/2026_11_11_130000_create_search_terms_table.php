<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What customers search for in the shop, counted, for "Popular searches".
 * Only the words and a count: nothing about who searched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_terms')) {
            return;
        }
        Schema::create('search_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 60)->unique();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_searched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_terms');
    }
};
