<?php

namespace Tests\Feature;

use App\Models\EnterpriseInquiry;
use App\Models\User;
use App\Models\VendorSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Admins can put a shop on any plan after confirming with the shop's name,
 * and the Enterprise "Talk to us" form reaches them as an inquiry.
 */
class AdminPlanChangeTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        foreach (['surname' => 'string', 'admin_role_id' => 'unsignedBigInteger', 'is_suspended' => 'boolean', 'last_seen_at' => 'timestamp'] as $column => $type) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $t) => $type === 'boolean' ? $t->boolean($column)->default(false) : $t->{$type}($column)->nullable());
            }
        }
        if (! Schema::hasColumn('vendor_applications', 'store_name')) {
            Schema::table('vendor_applications', fn (Blueprint $t) => $t->string('store_name')->nullable());
        }
        foreach (['admin_roles', 'user_notifications', 'admin_audit_logs', 'account_events', 'vendor_subscriptions', 'vendor_subscription_checkouts', 'enterprise_inquiries'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('admin_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('description')->nullable();
            $t->json('permissions');
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('broadcast_id')->nullable();
            $t->string('type');
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('link_url')->nullable();
            $t->boolean('important')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('admin_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('admin_id')->nullable();
            $t->string('admin_name')->nullable();
            $t->string('action')->nullable();
            $t->string('method', 10)->nullable();
            $t->string('path')->nullable();
            $t->string('target_id')->nullable();
            $t->string('target_label')->nullable();
            $t->unsignedSmallInteger('status_code')->nullable();
            $t->json('details')->nullable();
            $t->string('ip_address')->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('account_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('admin_id')->nullable();
            $t->string('action');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id')->unique();
            $t->string('plan_key');
            $t->string('status');
            foreach (['subscription_started_at', 'subscription_ends_at', 'trial_started_at', 'trial_ends_at', 'current_period_started_at',
                'current_period_ends_at', 'next_billing_at', 'cancelled_at', 'expired_at', 'paid_at'] as $c) {
                $t->timestamp($c)->nullable();
            }
            foreach (['paymongo_checkout_session_id', 'paymongo_payment_id', 'payment_status', 'paid_currency', 'billing_period'] as $c) {
                $t->string($c)->nullable();
            }
            $t->decimal('paid_amount', 12, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_subscription_checkouts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->string('plan_key');
            $t->string('reference_number')->nullable();
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('status');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
        Schema::create('enterprise_inquiries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id')->nullable();
            foreach (['name', 'shop_name', 'email', 'phone'] as $c) {
                $t->string($c);
            }
            $t->unsignedSmallInteger('branches')->nullable();
            $t->text('message');
            $t->string('status')->default('new');
            $t->text('admin_note')->nullable();
            $t->unsignedBigInteger('handled_by')->nullable();
            $t->timestamp('handled_at')->nullable();
            $t->timestamps();
        });

        $this->admin = $this->adminWith(['*'], 'admin@example.com');
    }

    private function adminWith(array $permissions, string $email): User
    {
        $role = DB::table('admin_roles')->insertGetId(['name' => $email, 'permissions' => json_encode($permissions)]);
        $admin = User::create(['name' => 'Admin', 'email' => $email, 'password' => Hash::make('x'), 'role' => User::ROLE_ADMIN]);
        $admin->forceFill(['admin_role_id' => $role])->save();

        return $admin;
    }

    private function shop(): User
    {
        $shop = User::create(['name' => 'John Doe', 'email' => 'flux@example.com', 'password' => 'x', 'role' => User::ROLE_VENDOR]);
        DB::table('vendor_applications')->insert(['email' => $shop->email, 'store_name' => 'Flux Flower Store', 'status' => 'approved']);

        return $shop;
    }

    public function test_an_admin_moves_a_shop_to_any_plan_after_typing_its_name(): void
    {
        $shop = $this->shop();
        VendorSubscription::create(['vendor_id' => $shop->id, 'plan_key' => 'professional', 'status' => 'active', 'subscription_ends_at' => now()->addDays(5)]);

        $this->signIn($this->admin)->postJson("/api/admin/vendors/{$shop->id}/plan", [
            'plan_key' => 'enterprise', 'months' => null, 'reason' => 'Signed the Enterprise contract', 'confirm' => 'flux flower store',
        ])->assertOk()->assertJsonPath('data.subscription.current.plan_key', 'enterprise');

        $sub = VendorSubscription::where('vendor_id', $shop->id)->sole();
        $this->assertSame('enterprise', $sub->plan_key);
        $this->assertTrue($sub->isActive());
        $this->assertNull($sub->subscription_ends_at);

        $event = DB::table('account_events')->where('user_id', $shop->id)->sole();
        $this->assertSame('plan_changed', $event->action);
        $this->assertStringContainsString('Professional → Enterprise with no end date', $event->reason);
        $this->assertSame('Your plan is now Enterprise', DB::table('user_notifications')->where('user_id', $shop->id)->value('title'));
    }

    public function test_the_plan_does_not_change_without_the_shop_name(): void
    {
        $shop = $this->shop();

        $this->signIn($this->admin)->postJson("/api/admin/vendors/{$shop->id}/plan", [
            'plan_key' => 'business', 'months' => 3, 'reason' => 'Promo for a partner', 'confirm' => 'Flux',
        ])->assertStatus(422)->assertJsonValidationErrors(['confirm']);

        $this->assertSame(0, VendorSubscription::count());
        $this->assertSame(0, DB::table('user_notifications')->count());
    }

    public function test_a_timed_plan_ends_after_the_chosen_months_and_none_removes_it(): void
    {
        $shop = $this->shop();

        $this->signIn($this->admin)->postJson("/api/admin/vendors/{$shop->id}/plan", [
            'plan_key' => 'business', 'months' => 3, 'reason' => 'Promo for a partner', 'confirm' => 'Flux Flower Store',
        ])->assertOk();
        $this->assertTrue(VendorSubscription::sole()->subscription_ends_at->isSameDay(now()->addMonthsNoOverflow(3)));

        $this->signIn($this->admin)->postJson("/api/admin/vendors/{$shop->id}/plan", [
            'plan_key' => 'none', 'reason' => 'Contract ended', 'confirm' => 'Flux Flower Store',
        ])->assertOk();
        $this->assertFalse(VendorSubscription::sole()->isActive());
    }

    public function test_only_admins_allowed_to_manage_plans_can_change_one(): void
    {
        $shop = $this->shop();
        $viewer = $this->adminWith(['vendors.view', 'vendors.manage'], 'ops@example.com');

        $this->signIn($viewer)->postJson("/api/admin/vendors/{$shop->id}/plan", [
            'plan_key' => 'starter', 'months' => 1, 'reason' => 'Testing access', 'confirm' => 'Flux Flower Store',
        ])->assertForbidden();
        $this->assertSame(0, VendorSubscription::count());
    }

    public function test_talk_to_us_creates_an_inquiry_the_admin_sees_and_updates(): void
    {
        $this->postJson('/api/enterprise-inquiries', [
            'name' => 'Ana Cruz', 'shop_name' => 'Bloom Co', 'email' => 'ana@bloom.test', 'phone' => '09171234567',
            'branches' => 4, 'message' => 'We run four branches and need one ERP for all of them.',
        ])->assertCreated();

        $inquiry = EnterpriseInquiry::sole();
        $this->assertSame('0917 123 4567', $inquiry->phone);
        $this->assertNull($inquiry->vendor_id);
        $this->assertSame('Enterprise inquiry from Bloom Co', DB::table('user_notifications')->where('user_id', $this->admin->id)->value('title'));

        $this->signIn($this->admin)->getJson('/api/admin/subscriptions/enterprise-inquiries')
            ->assertOk()->assertJsonPath('data.inquiries.0.shop_name', 'Bloom Co')->assertJsonPath('data.counts.new', 1);
        $this->signIn($this->admin)->postJson("/api/admin/subscriptions/enterprise-inquiries/{$inquiry->id}", ['status' => 'contacted', 'note' => 'Called, sending a quote'])
            ->assertOk();
        $this->assertSame('contacted', $inquiry->fresh()->status);
        $this->assertSame($this->admin->id, $inquiry->fresh()->handled_by);
    }

    public function test_a_signed_in_vendor_inquiry_is_linked_to_their_shop(): void
    {
        $shop = $this->shop();

        $this->signIn($shop)->postJson('/api/vendor/enterprise-inquiries', [
            'name' => 'John Doe', 'shop_name' => 'Flux Flower Store', 'email' => 'flux@example.com', 'phone' => '0917 123 4567',
            'message' => 'We are opening a second branch next month.',
        ])->assertCreated();

        $this->assertSame($shop->id, EnterpriseInquiry::sole()->vendor_id);
    }
}
