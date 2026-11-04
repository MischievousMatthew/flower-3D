<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wrappers, ribbons and fillers no longer have prices of their own: each
 * occasion's base price covers them. Clears the old prices so no screen shows
 * them. (Cards keep their per-size prices.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bouquet_customization_options')) {
            DB::table('bouquet_customization_options')
                ->whereIn('type', ['wrapper', 'ribbon', 'filler'])
                ->where('price', '!=', 0)
                ->update(['price' => 0]);
        }
    }

    public function down(): void
    {
        // The old prices aren't kept
    }
};
