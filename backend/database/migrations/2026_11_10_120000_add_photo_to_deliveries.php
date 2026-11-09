<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photo of the order, taken or attached before it goes out for delivery.
 * The customer sees it on their order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'photo_url')) {
                $table->text('photo_url')->nullable();
            }
            if (! Schema::hasColumn('deliveries', 'photo_public_id')) {
                $table->string('photo_public_id')->nullable();
            }
            if (! Schema::hasColumn('deliveries', 'photo_taken_at')) {
                $table->timestamp('photo_taken_at')->nullable();
            }
            if (! Schema::hasColumn('deliveries', 'photo_taken_by')) {
                $table->string('photo_taken_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            foreach (['photo_url', 'photo_public_id', 'photo_taken_at', 'photo_taken_by'] as $c) {
                if (Schema::hasColumn('deliveries', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
