<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions are Basic, Pro and Premium, edited by admins:
 * - subscription_plan_settings holds what an admin changed on a plan
 *   (name, price, description, highlights, limits), laid over the defaults
 *   in App\Subscriptions\SubscriptionPlans
 * - the Enterprise plan is gone: shops on it move to Premium (the plan with
 *   everything), unfinished Enterprise checkouts are cancelled, and the
 *   "Talk to us" inquiries table is dropped
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plan_settings')) {
            Schema::create('subscription_plan_settings', function (Blueprint $table) {
                $table->id();
                $table->string('plan_key', 40)->unique();
                $table->string('name', 40)->nullable();
                $table->decimal('monthly_price', 12, 2)->nullable();
                $table->string('description', 300)->nullable();
                $table->text('highlights')->nullable();
                $table->boolean('featured')->nullable();
                $table->text('resource_limits')->nullable();
                $table->string('updated_by_name')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('vendor_subscriptions')) {
            DB::table('vendor_subscriptions')->where('plan_key', 'enterprise')->update(['plan_key' => 'professional', 'updated_at' => now()]);
        }
        if (Schema::hasTable('vendor_subscription_checkouts')) {
            DB::table('vendor_subscription_checkouts')->where('plan_key', 'enterprise')->where('status', 'pending')
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
        }
        Schema::dropIfExists('enterprise_inquiries');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_settings');
        // Enterprise isn't brought back
    }
};
