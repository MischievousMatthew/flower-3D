<?php

namespace Tests\Feature;

use App\Models\PayMongoWebhookEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Finance → Profit & loss: revenue against costs with gross and operating
 * profit, counting flower costs, packaging, payment fees and recorded
 * expenses, month by month and year by year, and the costs that weigh most.
 */
class ProfitLossTest extends TestCase
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

    private function expense(User $vendor, string $category, float $amount, string $on): void
    {
        DB::table('vendor_expenses')->insert(['owner_id' => $vendor->id, 'category' => $category, 'description' => $category, 'amount' => $amount, 'spent_on' => $on, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * October:
     *  - GCash ₱1,000: roses that cost ₱400, PayMongo fee ₱25 saved on the order
     *  - cash on delivery ₱600: tulips with no cost price set; ₱100 of it refunded
     *  - Maya ₱500: lilies that cost ₱200, fee ₱12.50 only in the webhook log
     *  - recorded: ₱150 packaging, ₱800 rent, ₱900 of flowers (stock); ₱300 stock from a funding request
     * March 2025: one ₱700 sale.
     */
    private function shop(): User
    {
        $vendor = $this->shopOwner();
        $this->sale($vendor, 1000, 0, '2026-10-05 12:00:00', ['payment_method' => 'ewallet', 'paymongo_response' => json_encode(['fee' => 25, 'charged_amount' => 1000])], [['Roses', 1, 1000, null, 400]]);
        $tulips = $this->sale($vendor, 600, 0, '2026-10-08 12:00:00', ['payment_method' => 'cod'], [['Tulips', 1, 600, null, 0]]);
        $this->ledger($vendor, 'debit', 'refund', 100, '2026-10-09 12:00:00', $tulips);
        $lilies = $this->sale($vendor, 500, 0, '2026-10-10 12:00:00', ['payment_method' => 'ewallet'], [['Lilies', 1, 500, null, 200]]);
        PayMongoWebhookEvent::create(['event_id' => 'evt_l', 'event_type' => 'payment.paid', 'order_id' => $lilies, 'status' => 'processed', 'payload' => ['data' => ['attributes' => ['data' => ['id' => 'pay_l', 'attributes' => ['amount' => 50000, 'fee' => 1250, 'net_amount' => 48750]]]]]]);
        $this->expense($vendor, 'packaging', 150, '2026-10-03');
        $this->expense($vendor, 'rent', 800, '2026-10-01');
        $this->expense($vendor, 'flowers', 900, '2026-10-02');
        $this->ledger($vendor, 'debit', 'adjustment', 300, '2026-10-04 12:00:00', null, ['funding_request_id' => 7, 'product_name' => 'Ribbon']);
        $this->sale($vendor, 700, 300, '2025-03-15 12:00:00');

        return $vendor;
    }

    public function test_gross_and_operating_profit_count_flower_costs_packaging_and_payment_fees(): void
    {
        $vendor = $this->shop();

        $t = $this->signIn($vendor)->getJson('/api/vendor/books/profit-loss' . self::OCTOBER)->assertOk()->json('data.current');

        $this->assertEquals(2100, $t['sales']);
        $this->assertEquals(100, $t['refunds']);
        $this->assertEquals(2000, $t['net_sales']);
        // Roses ₱400 + lilies ₱200; the tulips have no cost price
        $this->assertEquals(600, $t['cogs']);
        $this->assertSame(1, $t['uncosted_items']);
        $this->assertEquals(1400, $t['gross_profit']);
        // ₱25 from the order + ₱12.50 from the webhook log
        $this->assertEquals(37.5, $t['payment_fees']);
        $this->assertSame(2, $t['fees_known']);
        // Packaging + rent + payment fees (flowers are stock)
        $this->assertEquals(987.5, $t['operating_expenses']);
        $this->assertEquals(412.5, $t['operating_profit']);
        $this->assertEquals(412.5, $t['net_profit']);
        $this->assertEquals(20.6, $t['net_margin']);
    }

    public function test_flower_costs_can_be_counted_when_bought_instead(): void
    {
        $vendor = $this->shop();

        $t = $this->signIn($vendor)->getJson('/api/vendor/books/profit-loss' . self::OCTOBER . '&basis=bought')->assertOk()->json('data.current');

        // ₱900 of flowers + ₱300 of stock bought in October
        $this->assertSame('bought', $t['cost_basis']);
        $this->assertEquals(1200, $t['cogs']);
        $this->assertEquals(600, $t['cogs_sold']);
        $this->assertSame(0, $t['uncosted_items']);
        $this->assertEquals(800, $t['gross_profit']);
        $this->assertEquals(-187.5, $t['net_profit']);
    }

    public function test_monthly_and_yearly_performance_and_the_biggest_costs(): void
    {
        $vendor = $this->shop();

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/profit-loss' . self::OCTOBER)->assertOk()->json('data');

        $this->assertCount(12, $data['monthly']);
        $this->assertSame('October 2026', $data['monthly'][11]['label']);
        $this->assertEquals(412.5, $data['monthly'][11]['net_profit']);
        $this->assertEquals(37.5, $data['monthly'][11]['payment_fees']);
        $this->assertCount(6, $data['trend']);

        $this->assertSame([2024, 2025, 2026], array_column($data['yearly'], 'year'));
        $this->assertEquals(700, $data['yearly'][1]['sales']);
        $this->assertEquals(400, $data['yearly'][1]['net_profit']);
        $this->assertTrue($data['yearly'][2]['to_date']);
        $this->assertEquals(2100, $data['yearly'][2]['sales']);

        $this->assertSame(['Rent', 'Cost of items sold', 'Packaging', 'Payment fees'], array_column($data['top_costs'], 'label'));
        $this->assertEquals(50.4, $data['top_costs'][0]['share']);
    }

    public function test_payment_fees_lower_the_dashboard_profit_too_and_staff_need_the_permission(): void
    {
        $vendor = $this->shop();

        $this->signIn($vendor)->getJson('/api/vendor/books/dashboard' . self::OCTOBER)->assertOk()->assertJsonPath('data.current.net_profit', 412.5);

        $this->app['auth']->forgetGuards();
        $this->signIn($this->financeStaff($vendor, ['finance_dashboard']))->getJson('/api/finance/profit-loss')->assertForbidden();
        $this->signIn($this->financeStaff($vendor, ['profit_loss']))->getJson('/api/finance/profit-loss' . self::OCTOBER)
            ->assertOk()->assertJsonPath('data.current.gross_profit', 1400);
    }
}
