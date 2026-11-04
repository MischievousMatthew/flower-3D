<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How big a wrapper's printed word is, as a percent of the normal size
 * (50 to 200). Null is the normal size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            if (! Schema::hasColumn('bouquet_customization_options', 'pattern_size')) {
                $table->unsignedSmallInteger('pattern_size')->nullable()->after('pattern_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            $table->dropColumn('pattern_size');
        });
    }
};
