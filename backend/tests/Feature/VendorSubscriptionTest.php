<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Models\VendorSubscription;
use App\Http\Middleware\EnsureSubscriptionModuleAccess;
use App\Http\Middleware\EnsureResourceLimit;
use App\Http\Middleware\EnsureEmployeeModuleAccess;
use App\Services\ResourceLimitService;
use App\Services\VendorSubscriptionService;
use App\Subscriptions\SubscriptionPlans;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use LogicException;
use Tests\TestCase;

class VendorSubscriptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The application has a historical MySQL-only migration unrelated to
        // subscriptions. Keep this Stage 2 suite portable on the configured
        // SQLite test database while still exercising real persistence.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->timestamps();
        });
        Schema::create('vendor_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained('users')->cascadeOnDelete();
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
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_subscription_id')->nullable()->constrained('vendor_subscriptions')->nullOnDelete();
            $table->string('plan_key');
            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->unique(['vendor_id', 'plan_key']);
        });
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('name');
            $table->string('location');
            $table->string('manager')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->softDeletes();
        });
        Schema::create('employee_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('module');
            $table->string('permission');
            $table->string('access')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('vendor_subscription_trials');
        Schema::dropIfExists('vendor_subscriptions');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('employee_module_permissions');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    private function vendor(): User
    {
        return User::create([
            'name' => 'Vendor Owner',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => User::ROLE_VENDOR,
        ]);
    }

    public function test_plans_modules_and_resource_limits_are_centralized(): void
    {
        $business = SubscriptionPlans::get(SubscriptionPlans::BUSINESS);

        $this->assertSame(2999.00, $business['monthly_price']);
        $this->assertTrue($business['free_trial_available']);
        $this->assertSame(1, $business['trial_duration_months']);
        $this->assertTrue(SubscriptionPlans::includesModule(SubscriptionPlans::BUSINESS, 'warehouse'));
        $this->assertSame(3, SubscriptionPlans::resourceLimit(SubscriptionPlans::PROFESSIONAL, 'branches'));
        $this->assertSame(30, SubscriptionPlans::resourceLimit(SubscriptionPlans::PROFESSIONAL, 'staff_employees'));
        $this->assertNull(SubscriptionPlans::resourceLimit(SubscriptionPlans::ENTERPRISE, 'warehouses'));
        $this->assertSame('Business', SubscriptionPlans::requiredPlanForModule('warehouse')['name']);
        $this->assertSame('Professional', SubscriptionPlans::requiredPlanForModule('payroll')['name']);
    }

    public function test_business_trial_is_linked_to_vendor_and_runs_for_one_calendar_month(): void
    {
        $vendor = $this->vendor();
        $start = Carbon::parse('2026-01-31 10:30:00', 'Asia/Manila');

        $subscription = app(VendorSubscriptionService::class)->startBusinessTrial($vendor, $start);

        $this->assertSame($vendor->id, $subscription->vendor_id);
        $this->assertSame(SubscriptionPlans::BUSINESS, $subscription->plan_key);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertTrue($subscription->trial_started_at->equalTo($start));
        $this->assertTrue($subscription->trial_ends_at->equalTo($start->copy()->addMonthNoOverflow()));
        $this->assertTrue($subscription->isTrialActive($start->copy()->addDays(10)));
        $this->assertTrue($subscription->isActive($start->copy()->addDays(10)));
        $this->assertTrue($subscription->isExpired($subscription->trial_ends_at));
        $this->assertFalse($subscription->isActive($subscription->trial_ends_at));
        $this->assertSame($subscription->id, $vendor->fresh()->subscription->id);
    }

    public function test_business_trial_cannot_be_claimed_again_after_cancellation_or_expiry(): void
    {
        $vendor = $this->vendor();
        $service = app(VendorSubscriptionService::class);
        $subscription = $service->startBusinessTrial($vendor, Carbon::parse('2026-01-01'));
        $subscription->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);

        $this->expectException(LogicException::class);
        $service->startBusinessTrial($vendor, Carbon::parse('2026-03-01'));
    }

    public function test_subscription_middleware_denies_direct_module_access_not_in_the_plan(): void
    {
        $vendor = $this->vendor();
        VendorSubscription::create([
            'vendor_id' => $vendor->id,
            'plan_key' => SubscriptionPlans::STARTER,
            'status' => SubscriptionStatus::Active,
            'subscription_started_at' => now(),
        ]);
        $request = Request::create('/api/procurement/supply-chain/warehouses', 'GET');
        $request->setUserResolver(fn () => $vendor);
        $middleware = app(EnsureSubscriptionModuleAccess::class);

        $allowed = $middleware->handle($request, fn () => response()->json(['ok' => true]), 'products');
        $blocked = $middleware->handle($request, fn () => response()->json(['ok' => true]), 'warehouse');

        $this->assertSame(200, $allowed->getStatusCode());
        $this->assertSame(403, $blocked->getStatusCode());
        $this->assertSame('subscription_module_unavailable', $blocked->getData(true)['code']);
    }

    public function test_resource_limits_allow_creation_until_the_limit_and_enterprise_is_unlimited(): void
    {
        $vendor = $this->vendor();
        $subscription = VendorSubscription::create([
            'vendor_id' => $vendor->id,
            'plan_key' => SubscriptionPlans::PROFESSIONAL,
            'status' => SubscriptionStatus::Active,
            'subscription_started_at' => now(),
        ]);
        $limits = app(ResourceLimitService::class);

        $this->assertSame(1, $limits->getResourceLimit($vendor, 'warehouses'));
        $this->assertSame(30, $limits->getResourceLimit($vendor, 'staff_employees'));
        $this->assertTrue($limits->canCreateResource($vendor, 'warehouses'));

        \DB::table('warehouses')->insert([
            'owner_id' => $vendor->id, 'name' => 'Main', 'location' => 'Manila', 'created_at' => now(),
        ]);
        $this->assertFalse($limits->canCreateResource($vendor, 'warehouses'));
        $this->expectException(\LogicException::class);
        $limits->assertCanCreateResource($vendor, 'warehouses');
    }

    public function test_enterprise_resource_limits_are_unlimited(): void
    {
        $vendor = $this->vendor();
        VendorSubscription::create([
            'vendor_id' => $vendor->id,
            'plan_key' => SubscriptionPlans::ENTERPRISE,
            'status' => SubscriptionStatus::Active,
            'subscription_started_at' => now(),
        ]);
        \DB::table('warehouses')->insert([
            'owner_id' => $vendor->id, 'name' => 'Main', 'location' => 'Manila', 'created_at' => now(),
        ]);

        $limits = app(ResourceLimitService::class);
        $this->assertNull($limits->getResourceLimit($vendor, 'warehouses'));
        $this->assertTrue($limits->canCreateResource($vendor, 'warehouses'));
        $this->assertTrue($limits->canCreateResource($vendor, 'staff_employees'));
    }

    public function test_resource_limit_middleware_blocks_a_direct_warehouse_create_request(): void
    {
        $vendor = $this->vendor();
        VendorSubscription::create([
            'vendor_id' => $vendor->id,
            'plan_key' => SubscriptionPlans::PROFESSIONAL,
            'status' => SubscriptionStatus::Active,
            'subscription_started_at' => now(),
        ]);
        \DB::table('warehouses')->insert([
            'owner_id' => $vendor->id, 'name' => 'Main', 'location' => 'Manila', 'created_at' => now(),
        ]);
        $request = Request::create('/api/procurement/supply-chain/warehouses', 'POST');
        $request->setUserResolver(fn () => $vendor);

        $response = app(EnsureResourceLimit::class)->handle(
            $request,
            fn () => response()->json(['created' => true], 201),
            'warehouses',
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Warehouse limit reached.', $response->getData(true)['message']);
        $this->assertStringContainsString('Professional plan includes 1 warehouse.', $response->getData(true)['detail']);
        $this->assertStringContainsString('Upgrade to Enterprise', $response->getData(true)['detail']);
    }

    public function test_employee_direct_module_access_requires_both_company_plan_and_employee_permission(): void
    {
        $vendor = $this->vendor();
        $subscription = VendorSubscription::create([
            'vendor_id' => $vendor->id,
            'plan_key' => SubscriptionPlans::PROFESSIONAL,
            'status' => SubscriptionStatus::Active,
            'subscription_started_at' => now(),
        ]);
        $employeeId = \DB::table('employees')->insertGetId([
            'owner_id' => $vendor->id, 'name' => 'John', 'email' => 'john@example.test', 'password' => 'x',
        ]);
        \DB::table('employee_module_permissions')->insert([
            'owner_id' => $vendor->id, 'employee_id' => $employeeId,
            'module' => 'warehouse', 'permission' => 'view', 'access' => 'granular',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $employee = \App\Models\Employee::findOrFail($employeeId);
        $request = Request::create('/api/procurement/supply-chain/warehouses', 'GET');
        $request->setUserResolver(fn () => $employee);
        $middleware = app(EnsureEmployeeModuleAccess::class);

        $allowed = $middleware->handle($request, fn () => response()->json(['ok' => true]), 'warehouse', 'view');
        $this->assertSame(200, $allowed->getStatusCode());

        $subscription->update(['plan_key' => SubscriptionPlans::STARTER]);
        $blocked = $middleware->handle($request, fn () => response()->json(['ok' => true]), 'warehouse', 'view');
        $this->assertSame(403, $blocked->getStatusCode());
        $this->assertSame('subscription_module_unavailable', $blocked->getData(true)['code']);
    }
}
