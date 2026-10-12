<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The suspension history now covers customers as well as vendors, and
     * also records admins viewing a customer's personal details.
     */
    public function up(): void
    {
        Schema::rename('vendor_account_events', 'account_events');
        Schema::table('account_events', function (Blueprint $table) {
            $table->renameColumn('vendor_id', 'user_id');
        });
    }

    public function down(): void
    {
        Schema::table('account_events', function (Blueprint $table) {
            $table->renameColumn('user_id', 'vendor_id');
        });
        Schema::rename('account_events', 'vendor_account_events');
    }
};
