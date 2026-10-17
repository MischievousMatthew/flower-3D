<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The vendor's occasion presets for the 3D bouquet customizer:
     * { key: { enabled, sizes[] } }. Null means the defaults in
     * App\Support\BouquetPresets.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('vendor_applications', 'bouquet_presets')) {
            Schema::table('vendor_applications', function (Blueprint $table) {
                $table->json('bouquet_presets')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vendor_applications', 'bouquet_presets')) {
            Schema::table('vendor_applications', function (Blueprint $table) {
                $table->dropColumn('bouquet_presets');
            });
        }
    }
};
