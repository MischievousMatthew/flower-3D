<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Talk to us" on the Enterprise plan: who asked, how to reach them, and
 * where the admin is with it (Admin → Subscription revenue → Enterprise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprise_inquiries', function (Blueprint $table) {
            $table->id();
            // Set when a signed-in vendor asks; visitors have no account yet
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('shop_name', 150);
            $table->string('email', 150);
            $table->string('phone', 20);
            $table->unsignedSmallInteger('branches')->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new')->index(); // new · contacted · won · closed
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprise_inquiries');
    }
};
