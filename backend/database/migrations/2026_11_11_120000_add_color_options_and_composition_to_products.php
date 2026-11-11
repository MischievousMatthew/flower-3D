<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two optional lists a shop can fill in for a product:
 * - color_options: the colours a customer can pick, e.g.
 *   [{"name": "Red", "hex": "#c0392b"}, {"name": "Pink", "hex": "#f4a7b9"}]
 * - composition: the flowers in the bouquet and how many of each, e.g.
 *   [{"flower": "Rose", "color": "Red", "count": 12}, {"flower": "Baby's breath", "count": 5}]
 * Both are empty until the shop adds them; the product page shows them only then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'color_options')) {
                $table->json('color_options')->nullable()->after('color');
            }
            if (! Schema::hasColumn('products', 'composition')) {
                $table->json('composition')->nullable()->after('color_options');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'composition')) {
                $table->dropColumn('composition');
            }
            if (Schema::hasColumn('products', 'color_options')) {
                $table->dropColumn('color_options');
            }
        });
    }
};
