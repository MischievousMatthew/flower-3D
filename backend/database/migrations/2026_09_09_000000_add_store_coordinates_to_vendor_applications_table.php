<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->decimal('store_latitude', 10, 7)->nullable()->after('store_address');
            $table->decimal('store_longitude', 10, 7)->nullable()->after('store_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropColumn(['store_latitude', 'store_longitude']);
        });
    }
};
