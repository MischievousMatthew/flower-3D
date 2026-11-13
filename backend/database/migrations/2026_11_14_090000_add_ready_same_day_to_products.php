<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product's preparation time is one of two things: "ready the same day"
 * (it can be delivered today whatever the shop's same-day switch says), or the
 * shop's own preparation time from its settings. Existing products follow the
 * shop's settings, as checkout always did.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'ready_same_day')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('ready_same_day')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'ready_same_day')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('ready_same_day');
            });
        }
    }
};
