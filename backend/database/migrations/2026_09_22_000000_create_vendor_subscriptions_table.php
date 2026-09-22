<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_subscriptions', function (Blueprint $table) {
            $table->id();
            // The approved vendor User is the existing company/owner identity.
            $table->foreignId('vendor_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('plan_key');
            $table->string('status')->index();

            $table->timestamp('subscription_started_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_started_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            // Reserved for the future vendor billing flow; no PayMongo calls in Stage 2.
            $table->string('paymongo_checkout_session_id')->nullable()->index();
            $table->string('paymongo_payment_id')->nullable()->index();
            $table->string('payment_status')->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->string('paid_currency', 3)->nullable();
            $table->string('billing_period')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'plan_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_subscriptions');
    }
};
