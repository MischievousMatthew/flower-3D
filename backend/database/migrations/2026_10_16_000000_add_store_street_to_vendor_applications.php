<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The shop's street, block and lot, kept apart from the city / province in store_address. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->string('store_street')->nullable()->after('store_address');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropColumn('store_street');
        });
    }
};
