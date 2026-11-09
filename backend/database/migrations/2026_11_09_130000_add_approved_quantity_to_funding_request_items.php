<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Finance can approve less of a product than Procurement asked for */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funding_request_items') && ! Schema::hasColumn('funding_request_items', 'approved_quantity')) {
            Schema::table('funding_request_items', function (Blueprint $table) {
                $table->integer('approved_quantity')->nullable()->after('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('funding_request_items', 'approved_quantity')) {
            Schema::table('funding_request_items', function (Blueprint $table) {
                $table->dropColumn('approved_quantity');
            });
        }
    }
};
