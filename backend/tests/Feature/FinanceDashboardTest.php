<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * The finance dashboard: sales, net revenue, expenses and estimated profit for the
 * period, money still on its way in or out, twelve months of revenue against
 * expenses, and the latest movements, for the shop owner and ERP Finance staff.
 */
class FinanceDashboardTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** October: ₱5,000 of sales costing ₱2,000, a ₱500 refund, ₱800 rent; September: ₱1,000 of sales */
    private function shopWithBooks(): User
    {
        $vendor = $this->shopOwner();
        $this->sale($vendor, 3000, 1200, '2026-10-05 12:00:00');
        $refunded = $this->sale($vendor, 2000, 800, '2026-10-12 12:00:00');
        $this->ledger($vendor, 'debit', 'refund', 500, '2026-10-14 12:00:00', $refunded);
        DB::table('vendor_expenses')->insert(['owner_id' => $vendor->id, 'category' => 'rent', 'description' => 'October rent', 'amount' => 800, 'spent_on' => '2026-10-01']);
        // Stock bought through a funding request is a purchase, not an extra expense (its cost counts when it sells)
        $this->ledger($vendor, 'debit', 'adjustment', 1500, '2026-10-03 12:00:00', null, ['funding_request_id' => 9, 'product_name' => 'Tulips']);
        $this->sale($vendor, 1000, 400, '2026-09-10 12:00:00');

        return $vendor;
    }

    public function test_the_period_shows_sales_net_revenue_expenses_and_estimated_profit(): void
    {
        $vendor = $this->shopWithBooks();

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/dashboard?from=2026-10-01&to=2026-10-31')->assertOk()->json('data');

        $this->assertEquals(5000, $data['current']['sales']);
        $this->assertEquals(500, $data['current']['refunds']);
        $this->assertEquals(4500, $data['current']['net_sales']);
        $this->assertEquals(2000, $data['current']['cogs']);
        $this->assertEquals(800, $data['current']['operating_expenses']);
        // 4,500 − 2,000 − 800
        $this->assertEquals(1700, $data['current']['net_profit']);
        $this->assertEquals(1500, $data['current']['stock_purchases']);
        // The same length of time just before: September
        $this->assertEquals(1000, $data['previous']['sales']);
    }

    public function test_cash_still_to_collect_and_refunds_still_to_pay_are_pending(): void
    {
        $vendor = $this->shopWithBooks();
        // Cash on delivery not yet collected counts; a collected or cancelled one doesn't
        $this->order($vendor, ['payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'processing', 'total_amount' => 1200]);
        $this->order($vendor, ['payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'cancelled', 'total_amount' => 999]);
        $this->order($vendor, ['payment_method' => 'cod', 'payment_status' => 'paid', 'status' => 'completed', 'total_amount' => 999]);
        $order = $this->order($vendor, ['total_amount' => 600]);
        // Agreed at ₱400, and one still being agreed at the ₱250 offer; a replacement costs no money back
        DB::table('order_requests')->insert([
            ['owner_id' => $vendor->id, 'order_id' => $order, 'status' => 'approved', 'settled_resolution' => 'partial_refund', 'settled_amount' => 400, 'offer_resolution' => null, 'offer_amount' => null, 'resolution_requested' => 'partial_refund', 'created_at' => now(), 'updated_at' => now()],
            ['owner_id' => $vendor->id, 'order_id' => $order, 'status' => 'pending', 'settled_resolution' => null, 'settled_amount' => null, 'offer_resolution' => 'partial_refund', 'offer_amount' => 250, 'resolution_requested' => 'full_refund', 'created_at' => now(), 'updated_at' => now()],
            ['owner_id' => $vendor->id, 'order_id' => $order, 'status' => 'approved', 'settled_resolution' => 'replacement', 'settled_amount' => null, 'offer_resolution' => null, 'offer_amount' => null, 'resolution_requested' => 'replacement', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/dashboard')->assertOk()->json('data');

        $this->assertEquals(['amount' => 1200, 'orders' => 1], $data['pending_payments']);
        $this->assertEquals(650, $data['pending_refunds']['amount']);
        $this->assertSame(2, $data['pending_refunds']['count']);
        $this->assertEquals(400, $data['pending_refunds']['agreed']);
        $this->assertEquals(250, $data['pending_refunds']['negotiating']);
    }

    public function test_twelve_months_of_revenue_and_expenses_and_the_latest_movements(): void
    {
        $vendor = $this->shopWithBooks();

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/dashboard?from=2026-10-01&to=2026-10-31')->assertOk()->json('data');

        $this->assertCount(12, $data['trend']);
        $this->assertSame('November 2025', $data['trend'][0]['label']);
        $october = $data['trend'][11];
        $this->assertSame('October 2026', $october['label']);
        $this->assertEquals(4500, $october['net_sales']);
        $this->assertEquals(2000, $october['cogs']);
        $this->assertEquals(800, $october['expenses']);
        $this->assertEquals(1000, $data['trend'][10]['sales']);

        // Newest first: the refund on the 14th leads
        $this->assertSame('refund', $data['recent'][0]['kind']);
        $this->assertCount(5, $data['recent']);
    }

    public function test_finance_staff_need_the_dashboard_permission_and_see_only_their_shop(): void
    {
        $vendor = $this->shopWithBooks();
        $otherShop = $this->shopOwner();
        $this->sale($otherShop, 99999, 0, '2026-10-06 12:00:00');

        $this->signIn($this->financeStaff($vendor))->getJson('/api/finance/dashboard')->assertForbidden();

        $data = $this->signIn($this->financeStaff($vendor, ['finance_dashboard']))
            ->getJson('/api/finance/dashboard?from=2026-10-01&to=2026-10-31')->assertOk()->json('data');
        $this->assertEquals(5000, $data['current']['sales']);

        // The vendor portal route stays owner-only
        $this->signIn($this->financeStaff($vendor, ['finance_dashboard']))->getJson('/api/vendor/books/dashboard')->assertForbidden();
    }
}
