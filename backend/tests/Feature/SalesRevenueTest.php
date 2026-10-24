<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Finance → Sales & revenue: gross sales, discounts and refunds kept apart,
 * cash-on-delivery against e-wallet sales, sales by date, product and order,
 * and how the orders placed in the period turned out.
 */
class SalesRevenueTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    private const OCTOBER = '?from=2026-10-01&to=2026-10-31';

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

    /**
     * October:
     *  - GCash ₱900: 2 roses at ₱400 (regular ₱500) + ₱100 delivery
     *  - cash on delivery ₱600: 1 tulip bunch at its regular price, ₱150 of it refunded
     *  - Maya ₱300 from before discounts were tracked (no regular price saved)
     *  - placed but not sold: one cancelled, one still in progress (COD to collect), one returned
     * September: one ₱1,000 sale.
     */
    private function shopWithSales(): array
    {
        $vendor = $this->shopOwner();
        $roses = $this->sale($vendor, 900, 0, '2026-10-05 12:00:00', ['payment_method' => 'gcash', 'delivery_fee' => 100, 'subtotal' => 800], [['Roses', 2, 400, 500, 150]]);
        $tulips = $this->sale($vendor, 600, 0, '2026-10-12 12:00:00', ['payment_method' => 'cod'], [['Tulips', 1, 600, 600, 250]]);
        $this->ledger($vendor, 'debit', 'refund', 150, '2026-10-14 12:00:00', $tulips);
        $this->sale($vendor, 300, 0, '2026-10-08 12:00:00', ['payment_method' => 'maya'], [['Lilies', 1, 300, null, 100]]);
        $this->order($vendor, ['status' => 'cancelled', 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'total_amount' => 500, 'created_at' => '2026-10-10 09:00:00']);
        $this->order($vendor, ['status' => 'processing', 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'total_amount' => 700, 'created_at' => '2026-10-18 09:00:00', 'delivery_contact_name' => 'Joy Villanueva']);
        $this->order($vendor, ['status' => 'refunded', 'payment_method' => 'gcash', 'payment_status' => 'refunded', 'total_amount' => 450, 'created_at' => '2026-10-09 09:00:00']);
        $this->sale($vendor, 1000, 0, '2026-09-20 12:00:00');

        return [$vendor, $roses, $tulips];
    }

    public function test_gross_sales_discounts_delivery_and_refunds_are_kept_apart(): void
    {
        [$vendor] = $this->shopWithSales();

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/sales' . self::OCTOBER)->assertOk()->json('data');
        $now = $data['current'];

        $this->assertSame(3, $now['orders']);
        // 1,800 collected − 100 delivery + 200 discount (2 roses × ₱100 off)
        $this->assertEquals(1900, $now['gross_sales']);
        $this->assertEquals(200, $now['discounts']);
        $this->assertEquals(100, $now['delivery_fees']);
        $this->assertEquals(1800, $now['collected']);
        $this->assertEquals(150, $now['refunds']);
        $this->assertEquals(1650, $now['net_sales']);
        $this->assertEquals(600, $now['average_order']);
        // The Maya sale predates discount tracking
        $this->assertTrue($now['discounts_partial']);
        $this->assertEquals(1000, $data['previous']['collected']);
    }

    public function test_cash_on_delivery_and_e_wallet_sales_are_tracked_separately(): void
    {
        [$vendor] = $this->shopWithSales();

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/sales' . self::OCTOBER)->assertOk()->json('data');

        $this->assertEquals(['amount' => 600, 'orders' => 1], $data['current']['cod']);
        $this->assertEquals(['amount' => 1200, 'orders' => 2], $data['current']['ewallet']);
        $this->assertEquals([['method' => 'gcash', 'amount' => 900, 'orders' => 1], ['method' => 'maya', 'amount' => 300, 'orders' => 1]], $data['ewallet_methods']);
        // The order still in progress hasn't been paid for yet
        $this->assertEquals(['amount' => 700, 'orders' => 1], $data['cod_uncollected']);
    }

    public function test_completed_and_cancelled_orders_and_sales_by_date_and_product(): void
    {
        [$vendor] = $this->shopWithSales();

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/sales' . self::OCTOBER)->assertOk()->json('data');

        $outcomes = $data['outcomes'];
        $this->assertSame(6, $outcomes['placed']);
        $this->assertEquals(['orders' => 3, 'amount' => 1800], $outcomes['groups']['completed']);
        $this->assertEquals(['orders' => 1, 'amount' => 500], $outcomes['groups']['cancelled']);
        $this->assertEquals(['orders' => 1, 'amount' => 700], $outcomes['groups']['in_progress']);
        $this->assertEquals(['orders' => 1, 'amount' => 450], $outcomes['groups']['returned']);
        $this->assertEquals(16.7, $outcomes['cancellation_rate']);

        $this->assertSame('day', $data['by_date']['unit']);
        $this->assertCount(31, $data['by_date']['rows']);
        $oct5 = $data['by_date']['rows'][4];
        $this->assertSame('Mon, Oct 5', $oct5['label']);
        $this->assertEquals(1000, $oct5['gross_sales']);
        $this->assertEquals(900, $oct5['ewallet']);
        $this->assertEquals(150, $data['by_date']['rows'][13]['refunds']);

        $this->assertSame(['Roses', 'Tulips', 'Lilies'], array_column($data['by_product'], 'name'));
        $this->assertSame(2, $data['by_product'][0]['quantity']);
        $this->assertEquals(800, $data['by_product'][0]['sales']);
        $this->assertEquals(200, $data['by_product'][0]['discounts']);
    }

    public function test_the_order_list_filters_by_outcome_payment_and_search(): void
    {
        [$vendor, $roses, $tulips] = $this->shopWithSales();
        $signedIn = $this->signIn($vendor);

        $all = $signedIn->getJson('/api/vendor/books/sales/orders' . self::OCTOBER)->assertOk()->json('data');
        $this->assertSame(6, $all['meta']['total']);
        $byId = collect($all['orders'])->keyBy('id');
        $this->assertEquals(200, $byId[$roses]['discount']);
        $this->assertEquals(150, $byId[$tulips]['refunded']);
        $this->assertSame('completed', $byId[$tulips]['status_group']);

        $cancelled = $signedIn->getJson('/api/vendor/books/sales/orders' . self::OCTOBER . '&status=cancelled')->json('data.orders');
        $this->assertCount(1, $cancelled);
        $this->assertEquals(500, $cancelled[0]['total']);

        $this->assertSame(3, $signedIn->getJson('/api/vendor/books/sales/orders' . self::OCTOBER . '&payment=cod')->json('data.meta.total'));
        $found = $signedIn->getJson('/api/vendor/books/sales/orders' . self::OCTOBER . '&search=villanueva')->json('data.orders');
        $this->assertCount(1, $found);
        $this->assertSame('in_progress', $found[0]['status_group']);
    }

    public function test_finance_staff_need_the_sales_permission(): void
    {
        [$vendor] = $this->shopWithSales();

        // The finance overview alone doesn't open sales
        $this->signIn($this->financeStaff($vendor, ['finance_dashboard']))->getJson('/api/finance/sales')->assertForbidden();
        $this->signIn($this->financeStaff($vendor))->getJson('/api/finance/sales/orders')->assertForbidden();

        $staff = $this->financeStaff($vendor, ['sales_revenue']);
        $this->signIn($staff)->getJson('/api/finance/sales' . self::OCTOBER)->assertOk()->assertJsonPath('data.current.collected', 1800);
        $this->signIn($staff)->getJson('/api/finance/sales/orders' . self::OCTOBER)->assertOk()->assertJsonPath('data.meta.total', 6);
    }
}
