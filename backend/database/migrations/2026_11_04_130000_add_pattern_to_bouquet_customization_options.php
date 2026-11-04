<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A wrapper can carry a print: newspaper, or a word written over and over on
 * its colour (e.g. "love"). Null is plain paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            if (! Schema::hasColumn('bouquet_customization_options', 'pattern')) {
                $table->string('pattern', 20)->nullable()->after('color_hex');
            }
            if (! Schema::hasColumn('bouquet_customization_options', 'pattern_text')) {
                $table->string('pattern_text', 40)->nullable()->after('pattern');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            $table->dropColumn(['pattern', 'pattern_text']);
        });
    }
};
