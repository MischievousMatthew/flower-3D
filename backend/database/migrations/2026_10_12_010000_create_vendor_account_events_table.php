<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * History of a vendor account being suspended or reactivated, by whom
     * (null when automatic, e.g. after three product-report strikes) and why.
     */
    public function up(): void
    {
        Schema::create('vendor_account_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30); // suspended | reactivated
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_account_events');
    }
};
