<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Finance paying a supplier through PayMongo Checkout: one row per attempt */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funding_payment_checkouts')) {
            return;
        }
        Schema::create('funding_payment_checkouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index();
            $table->foreignId('funding_request_id')->constrained('funding_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            // pending | paid | cancelled | failed | needs_review
            $table->string('status', 20)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('payment_channel', 60);
            $table->string('reference_number', 60)->unique();
            $table->string('paymongo_checkout_session_id')->nullable()->index();
            $table->string('paymongo_payment_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('paid_by_name')->nullable();
            $table->unsignedBigInteger('funding_request_payment_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_payment_checkouts');
    }
};
