<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery areas are a comma list of cities; picking many of them runs past
 * 255 characters (all of Cavite and Metro Manila's cities is ~410), which
 * made saving the shop fail. Text holds any selection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->text('service_areas')->change();
        });
    }

    public function down(): void
    {
        // Left as text: shortening it could cut off shops' saved areas
    }
};
