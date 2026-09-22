<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_subscription_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_subscription_id')->nullable()->constrained('vendor_subscriptions')->nullOnDelete();
            $table->string('plan_key');
            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            // A vendor may consume each plan's trial only once, even after cancellation.
            $table->unique(['vendor_id', 'plan_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_subscription_trials');
    }
};
