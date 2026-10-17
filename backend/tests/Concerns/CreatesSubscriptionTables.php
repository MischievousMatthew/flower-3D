<?php

namespace Tests\Concerns;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Models\VendorSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For tests that build their own tables: vendor pages and plan-gated routes
 * read the vendor's subscription, so they need these tables (and, for
 * plan-gated routes, an active plan).
 */
trait CreatesSubscriptionTables
{
    protected function createSubscriptionTables(): void
    {
        Schema::dropIfExists('vendor_subscription_trials');
        Schema::dropIfExists('vendor_subscriptions');

        Schema::create('vendor_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->unique();
            $table->string('plan_key');
            $table->string('status');
            $table->timestamp('subscription_started_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_started_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('paymongo_checkout_session_id')->nullable();
            $table->string('paymongo_payment_id')->nullable();
            $table->string('payment_status')->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->string('paid_currency', 3)->nullable();
            $table->string('billing_period')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_subscription_trials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('vendor_subscription_id')->nullable();
            $table->string('plan_key');
            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });

        // Plan usage limits count these (only made if the test hasn't already)
        foreach (['warehouses', 'employees', 'products'] as $name) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('owner_id')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }
    }

    /** An active plan for the vendor (Business by default) */
    protected function giveActivePlan(User $vendor, string $plan = 'business'): VendorSubscription
    {
        return VendorSubscription::create([
            'vendor_id' => $vendor->id,
            'plan_key' => $plan,
            'status' => SubscriptionStatus::Active,
            'subscription_started_at' => now()->subDay(),
            'subscription_ends_at' => now()->addMonth(),
        ]);
    }
}
