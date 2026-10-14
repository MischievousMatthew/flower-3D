<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subscription & account pages: shop preferences (pause orders, cash on
     * delivery) and when a password was last changed (Security settings).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('vendor_applications', 'shop_preferences')) {
            Schema::table('vendor_applications', function (Blueprint $table) {
                $table->json('shop_preferences')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('password_changed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vendor_applications', 'shop_preferences')) {
            Schema::table('vendor_applications', function (Blueprint $table) {
                $table->dropColumn('shop_preferences');
            });
        }

        if (Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }
    }
};
