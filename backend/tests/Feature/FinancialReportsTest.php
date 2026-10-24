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
 * Finance → Reports: daily to yearly sales, expenses, profit and loss,
 * payments and refunds, and tax summaries, as JSON and as CSV or PDF downloads.
 */
class FinancialReportsTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    private const OCTOBER = 'from=2026-10-01&to=2026-10-31';

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

    /** October: ₱1,120 GCash sale + ₱600 cash sale, ₱120 refunded; ₱800 rent and ₱500 flowers recorded; August: ₱1,000 sale */
    private function shop(): User
    {
        $vendor = $this->shopOwner();
        $this->sale($vendor, 1120, 0, '2026-10-05 12:00:00', ['payment_method' => 'ewallet', 'paymongo_response' => json_encode(['fee' => 28, 'charged_amount' => 1120, 'source' => 'gcash'])], [['Roses', 2, 560, 600, 200]]);
        $cod = $this->sale($vendor, 600, 0, '2026-10-12 12:00:00', ['payment_method' => 'cod'], [['Tulips', 1, 600, 600, 250]]);
        $this->ledger($vendor, 'debit', 'refund', 120, '2026-10-14 12:00:00', $cod);
        foreach ([['rent', 'October rent', 800, '2026-10-01'], ['flowers', 'Roses from Dangwa', 500, '2026-10-02']] as [$cat, $what, $amount, $on]) {
            DB::table('vendor_expenses')->insert(['owner_id' => $vendor->id, 'category' => $cat, 'description' => $what, 'amount' => $amount, 'spent_on' => $on, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->sale($vendor, 1000, 0, '2026-08-10 12:00:00');

        return $vendor;
    }

    private function report($api, string $type, string $query = '')
    {
        return $api->getJson("/api/vendor/books/financial-reports/{$type}?" . self::OCTOBER . $query)->assertOk()->json('data');
    }

    private function summary(array $report): array
    {
        return array_column($report['summary'], 1, 0);
    }

    public function test_sales_reports_come_daily_weekly_monthly_or_yearly(): void
    {
        $api = $this->signIn($this->shop());

        $daily = $this->report($api, 'sales');
        $this->assertSame('Daily sales', $daily['title']);
        $this->assertCount(31, $daily['tables'][0]['rows']);
        $s = $this->summary($daily);
        // ₱1,720 collected + ₱80 of discount (2 roses ₱40 off)
        $this->assertEquals(1800, $s['Gross sales']);
        $this->assertEquals(80, $s['Discounts']);
        $this->assertEquals(1600, $s['Net sales']);
        $this->assertEquals(600, $s['Cash on delivery']);

        $this->assertCount(5, $this->report($api, 'sales', '&unit=week')['tables'][0]['rows']);
        $this->assertCount(1, $this->report($api, 'sales', '&unit=month')['tables'][0]['rows']);
        $yearly = $api->getJson('/api/vendor/books/financial-reports/sales?from=2026-01-01&to=2026-12-31&unit=year')->assertOk()->json('data.tables.0');
        $this->assertCount(1, $yearly['rows']);
        $this->assertEquals(2720, $yearly['totals']['gross_sales'] - 80);

        // Daily rows over a year would be too long to read
        $api->getJson('/api/vendor/books/financial-reports/sales?from=2026-01-01&to=2026-12-31&unit=day')->assertStatus(422);
    }

    public function test_expense_and_profit_and_loss_reports(): void
    {
        $api = $this->signIn($this->shop());

        $expenses = $this->report($api, 'expenses');
        $e = $this->summary($expenses);
        $this->assertEquals(1300, $e['Total recorded']);
        $this->assertEquals(800, $e['Running costs']);
        $this->assertEquals(500, $e['Flowers bought']);
        $this->assertEquals(28, $e['Payment fees']);
        $this->assertSame(2, $e['Missing a receipt']);
        $this->assertSame(['Rent', 'Flowers'], array_column($expenses['tables'][0]['rows'], 'label'));
        $this->assertCount(2, $expenses['tables'][1]['rows']);

        $pl = $this->report($api, 'profit_loss', '&unit=month');
        $p = $this->summary($pl);
        // 1,600 net − 650 cost of goods = 950; − 800 rent − 28 fees = 122
        $this->assertEquals(950, $p['Gross profit']);
        $this->assertEquals(122, $p['Operating profit']);
        $this->assertSame('Operating profit (estimated)', end($pl['tables'][0]['rows'])['label']);
    }

    public function test_payments_and_refunds_summary(): void
    {
        $api = $this->signIn($this->shop());

        $report = $this->report($api, 'payments_refunds');
        $s = $this->summary($report);

        $this->assertEquals(1720, $s['Successful payments']);
        $this->assertEquals(28, $s['PayMongo fees']);
        $this->assertEquals(120, $s['Refunded']);
        $this->assertSame('GCash', $report['tables'][1]['rows'][0]['label']);
    }

    public function test_tax_summaries_estimate_percentage_tax_vat_or_the_8_percent_option(): void
    {
        $api = $this->signIn($this->shop());

        $percentage = $this->summary($this->report($api, 'tax'));
        $this->assertEquals(1720, $percentage['Gross receipts']);
        $this->assertEquals(1600, $percentage['Net taxable sales']);
        $this->assertEquals(48, $percentage['Estimated tax']);
        $this->assertEquals(171.43, $this->summary($this->report($api, 'tax', '&tax_mode=vat'))['Estimated tax']);
        $this->assertEquals(128, $this->summary($this->report($api, 'tax', '&tax_mode=income8'))['Estimated tax']);

        // A long period is grouped by BIR quarter
        $year = $api->getJson('/api/vendor/books/financial-reports/tax?from=2026-07-01&to=2026-12-31&unit=month')->assertOk()->json('data.tables.0.rows');
        $this->assertSame(['Q3 2026', 'Q4 2026'], array_column($year, 'label'));
        $this->assertEquals(1000, $year[0]['net']);
    }

    public function test_reports_download_as_csv_or_pdf_and_staff_need_export(): void
    {
        $vendor = $this->shop();
        $api = $this->signIn($vendor);

        $csv = $api->get('/api/vendor/books/financial-reports/sales/export?format=csv&' . self::OCTOBER);
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('Daily sales', $csv->getContent());
        $this->assertStringContainsString('"Gross sales",1800', $csv->getContent());

        $pdf = $api->get('/api/vendor/books/financial-reports/profit_loss/export?format=pdf&' . self::OCTOBER);
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->app['auth']->forgetGuards();
        $viewer = $this->financeStaff($vendor, ['financial_reports']);
        $this->signIn($viewer)->getJson('/api/finance/reports/tax?' . self::OCTOBER)->assertOk();
        $this->signIn($viewer)->get('/api/finance/reports/tax/export?format=csv&' . self::OCTOBER, ['Accept' => 'application/json'])->assertForbidden();
        DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $viewer->id, 'module' => 'financial_reports', 'permission' => 'export', 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        $this->signIn($viewer)->get('/api/finance/reports/tax/export?format=csv&' . self::OCTOBER)->assertOk();
    }
}
