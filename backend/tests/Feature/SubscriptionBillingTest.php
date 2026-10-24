<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Models\VendorSubscription;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Finance → Subscription & billing: ERP Finance staff read the shop's
 * BloomCraft plan, renewal, trials, charges and invoices from the existing
 * subscription records, without changing anything.
 */
class SubscriptionBillingTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    private int $ref = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        Schema::dropIfExists('vendor_subscription_checkouts');
        Schema::create('vendor_subscription_checkouts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->string('plan_key');
            $t->string('status');
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3)->default('PHP');
            $t->string('billing_period')->default('monthly');
            $t->string('reference_number')->unique();
            $t->string('payment_attempt')->unique();
            $t->string('paymongo_checkout_session_id')->nullable();
            $t->string('paymongo_payment_id')->nullable();
            $t->text('checkout_url')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function checkout(User $vendor, string $status, float $amount, string $at, ?string $paidAt = null): int
    {
        $this->ref++;

        return DB::table('vendor_subscription_checkouts')->insertGetId([
            'vendor_id' => $vendor->id, 'plan_key' => 'professional', 'status' => $status, 'amount' => $amount,
            'reference_number' => 'SUB-' . $this->ref, 'payment_attempt' => 'att-' . $this->ref, 'paymongo_payment_id' => 'pay_abcdef12345' . $this->ref,
            'checkout_url' => $status === 'pending' ? 'https://checkout.paymongo.com/x' : null, 'paid_at' => $paidAt,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    /** Professional plan renewing Nov 15; paid in Sep and Oct, one failed, one left to expire, one open checkout, and an earlier trial */
    private function shop(): array
    {
        $vendor = $this->shopOwner();
        VendorSubscription::where('vendor_id', $vendor->id)->update([
            'subscription_started_at' => '2026-09-15 10:00:00', 'current_period_started_at' => '2026-10-15 10:00:00', 'subscription_ends_at' => '2026-11-15 10:00:00',
        ]);
        $paid = $this->checkout($vendor, 'paid', 1499, '2026-10-15 09:55:00', '2026-10-15 10:00:00');
        $this->checkout($vendor, 'paid', 1499, '2026-09-15 09:55:00', '2026-09-15 10:00:00');
        $this->checkout($vendor, 'failed', 1499, '2026-10-14 09:00:00');
        $this->checkout($vendor, 'pending', 1499, '2026-10-10 09:00:00');
        $this->checkout($vendor, 'pending', 1499, '2026-10-20 08:00:00');
        DB::table('vendor_subscription_trials')->insert(['vendor_id' => $vendor->id, 'plan_key' => 'business', 'started_at' => '2026-08-01 10:00:00', 'ends_at' => '2026-08-15 10:00:00', 'created_at' => now(), 'updated_at' => now()]);

        return [$vendor, $paid];
    }

    private function staff(User $vendor, bool $allowed)
    {
        return $this->signIn($this->financeStaff($vendor, $allowed ? ['subscription_billing'] : []));
    }

    public function test_finance_sees_the_current_plan_and_renewal(): void
    {
        [$vendor] = $this->shop();

        $current = $this->staff($vendor, true)->getJson('/api/finance/subscription')->assertOk()->json('data.current');

        $this->assertSame('professional', $current['plan_key']);
        $this->assertSame('active', $current['status']);
        $this->assertTrue($current['active']);
        $this->assertStringStartsWith('2026-11-15', $current['ends_at']);
        $this->assertSame(26, $current['days_left']);
    }

    public function test_charges_payment_history_failed_payments_and_trials(): void
    {
        [$vendor] = $this->shop();

        $data = $this->staff($vendor, true)->getJson('/api/finance/billing')->assertOk()->json('data');
        $s = $data['summary'];

        $this->assertEquals(2998, $s['total_paid']);
        $this->assertSame(2, $s['payments']);
        $this->assertStringStartsWith('2026-11-15', $s['renews_at']);
        // One failed, one checkout left unpaid for over a day; the fresh one is still pending
        $this->assertSame(2, $s['failed']);
        $this->assertEquals(2998, $s['failed_amount']);
        $this->assertSame(1, $s['pending']);
        $this->assertSame(1, $s['trials']);
        $this->assertCount(6, $data['items']);
        $this->assertSame(['pending', 'paid', 'failed', 'expired', 'paid', 'trial'], array_column($data['items'], 'status'));
    }

    public function test_invoices_open_for_paid_charges_of_this_shop_only(): void
    {
        [$vendor, $paid] = $this->shop();
        [$other, $otherPaid] = $this->shop();

        $invoice = $this->staff($vendor, true)->getJson("/api/finance/billing/{$paid}/invoice")->assertOk()->json('data');
        $this->assertEquals(1499, $invoice['total']);
        $this->assertSame('paid', $invoice['status']);
        $this->assertSame('••••' . substr('pay_abcdef12345' . 1, -6), $invoice['payment']['reference']);

        $this->app['auth']->forgetGuards();
        $this->staff($vendor, true)->getJson("/api/finance/billing/{$otherPaid}/invoice")->assertNotFound();
    }

    public function test_a_running_trial_is_tracked(): void
    {
        $vendor = $this->shopOwner();
        VendorSubscription::where('vendor_id', $vendor->id)->update(['status' => SubscriptionStatus::Trialing, 'plan_key' => 'business', 'trial_started_at' => '2026-10-10 10:00:00', 'trial_ends_at' => '2026-10-24 10:00:00']);

        $current = $this->staff($vendor, true)->getJson('/api/finance/subscription')->assertOk()->json('data.current');
        $this->assertTrue($current['trial']);
        $this->assertSame(4, $current['days_left']);
        $this->assertStringStartsWith('2026-10-24', $this->staff($vendor, true)->getJson('/api/finance/billing')->json('data.summary.trial_ends_at'));
    }

    public function test_staff_need_the_permission_and_the_owner_keeps_their_own_pages(): void
    {
        [$vendor] = $this->shop();

        $this->staff($vendor, false)->getJson('/api/finance/billing')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->signIn($vendor)->getJson('/api/vendor/account/billing')->assertOk()->assertJsonPath('data.summary.payments', 2);
    }
}
