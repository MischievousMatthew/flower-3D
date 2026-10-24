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
 * Finance → Refunds: refund cases with their decisions and status, linked to
 * the order and payment, recording the payout, and reconciling each refund
 * against the books.
 */
class RefundsTest extends TestCase
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

    private function refundCase(User $vendor, int $orderId, array $attrs): int
    {
        return DB::table('order_requests')->insertGetId($attrs + [
            'owner_id' => $vendor->id, 'order_id' => $orderId, 'type' => 'refund', 'reason_category' => 'damaged', 'reason' => 'Petals wilted on arrival',
            'resolution_requested' => 'full_refund', 'created_at' => '2026-10-10 09:00:00', 'updated_at' => now(),
        ]);
    }

    /** Seven refund cases at each stage, one replacement, and a refund on the books with no case */
    private function shop(): array
    {
        $vendor = $this->shopOwner();
        $o = fn (float $amount, string $at) => $this->sale($vendor, $amount, 0, $at, ['payment_method' => 'ewallet']);
        $c = [];

        // Completed and on the books
        $order = $o(1000, '2026-10-02 12:00:00');
        DB::table('orders')->where('id', $order)->update(['payment_status' => 'refunded']);
        $c['done'] = $this->refundCase($vendor, $order, ['status' => 'completed', 'settled_resolution' => 'full_refund', 'settled_amount' => 1000, 'refund_method' => 'gcash', 'refund_reference' => 'GC-77', 'refund_sent_at' => '2026-10-12 09:00:00', 'completed_at' => '2026-10-13 09:00:00']);
        $this->ledger($vendor, 'debit', 'refund', 1000, '2026-10-13 09:00:00', $order, ['order_request_id' => $c['done']]);
        // Completed, but never reached the books
        $c['unbooked'] = $this->refundCase($vendor, $o(600, '2026-10-03 12:00:00'), ['status' => 'completed', 'resolution_requested' => 'partial_refund', 'amount_requested' => 200, 'settled_resolution' => 'partial_refund', 'settled_amount' => 200, 'refund_sent_at' => '2026-10-14 09:00:00', 'completed_at' => '2026-10-15 09:00:00']);
        // Agreed, waiting for the shop to pay it
        $c['approved'] = $this->refundCase($vendor, $o(800, '2026-10-04 12:00:00'), ['status' => 'approved', 'resolution_requested' => 'partial_refund', 'amount_requested' => 300, 'settled_resolution' => 'partial_refund', 'settled_amount' => 300, 'settled_at' => '2026-10-15 09:00:00']);
        // Sent ten days ago and still not confirmed
        $c['stale'] = $this->refundCase($vendor, $o(500, '2026-10-05 12:00:00'), ['status' => 'approved', 'settled_resolution' => 'full_refund', 'settled_amount' => 500, 'refund_method' => 'maya', 'refund_sent_at' => '2026-10-10 10:00:00']);
        $c['requested'] = $this->refundCase($vendor, $o(400, '2026-10-06 12:00:00'), ['status' => 'pending']);
        $c['declined'] = $this->refundCase($vendor, $o(300, '2026-10-07 12:00:00'), ['status' => 'rejected', 'closed_reason' => 'Photos show the bouquet was fine']);
        // A replacement moves no money, so it isn't a refund
        $this->refundCase($vendor, $o(700, '2026-10-08 12:00:00'), ['status' => 'approved', 'resolution_requested' => 'replacement', 'settled_resolution' => 'replacement']);
        // Opened in September and still open
        $c['carried'] = $this->refundCase($vendor, $o(250, '2026-09-01 12:00:00'), ['status' => 'pending', 'created_at' => '2026-09-02 09:00:00']);
        // A refund on the books with no case behind it
        $this->ledger($vendor, 'debit', 'refund', 150, '2026-10-12 12:00:00', $o(450, '2026-10-09 12:00:00'));

        return [$vendor, $c];
    }

    public function test_refund_cases_are_tracked_from_request_to_completion(): void
    {
        [$vendor, $c] = $this->shop();

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/refund-cases' . self::OCTOBER)->assertOk()->json('data');
        $s = $data['summary']['statuses'];

        $this->assertSame(2, $s['requested']['count']);
        $this->assertSame(1, $s['approved']['count']);
        $this->assertSame(1, $s['processing']['count']);
        $this->assertSame(2, $s['completed']['count']);
        $this->assertSame(1, $s['closed']['count']);
        $this->assertSame(7, $data['meta']['total']);
        // ₱1,000 confirmed + ₱150 on the books with no case
        $this->assertEquals(1150, $data['summary']['refunded']);
        $this->assertEquals(800, $data['summary']['owed']);
        $this->assertSame('Arrived damaged or wilted', $data['summary']['reasons'][0]['label']);

        $rows = collect($data['rows'])->keyBy('id');
        $this->assertTrue($rows[$c['carried']]['carried_over']);
        $this->assertSame('Photos show the bouquet was fine', $rows[$c['declined']]['closed_reason']);
        $this->assertEquals(400, $rows[$c['requested']]['amount']);
    }

    public function test_each_refund_links_to_its_order_payment_and_books_entry(): void
    {
        [$vendor, $c] = $this->shop();

        $row = collect($this->signIn($vendor)->getJson('/api/vendor/books/refund-cases' . self::OCTOBER)->json('data.rows'))->firstWhere('id', $c['done']);

        $this->assertEquals(1000, $row['order']['total']);
        $this->assertEquals(1000, $row['order']['payment_booked']);
        $this->assertSame('refunded', $row['order']['payment_status']);
        $this->assertEquals(1000, $row['books']['amount']);
        $this->assertStringStartsWith('2026-10-13', $row['books']['at']);
        $this->assertSame('GC-77', $row['refund_reference']);
        $this->assertSame([], $row['issues']);
    }

    public function test_reconciliation_flags_refunds_that_dont_match_the_books(): void
    {
        [$vendor, $c] = $this->shop();

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/refund-cases' . self::OCTOBER)->json('data');
        $rec = $data['reconciliation'];

        $this->assertSame(4, $rec['checked']);
        $this->assertSame(2, $rec['matched']);
        $this->assertSame(3, $rec['issue_count']);
        $this->assertEquals(150, $rec['unmatched_books'][0]['amount']);

        $flagged = collect($this->signIn($vendor)->getJson('/api/vendor/books/refund-cases' . self::OCTOBER . '&issues=1')->json('data.rows'))->keyBy('id');
        $this->assertSame('missing_in_books', $flagged[$c['unbooked']]['issues'][0]['code']);
        $this->assertSame('unconfirmed', $flagged[$c['stale']]['issues'][0]['code']);
    }

    public function test_finance_records_the_refund_as_sent_and_staff_need_edit_for_it(): void
    {
        [$vendor, $c] = $this->shop();

        $viewer = $this->financeStaff($vendor, ['refunds']);
        $this->signIn($viewer)->getJson('/api/finance/refunds' . self::OCTOBER)->assertOk()->assertJsonPath('data.meta.total', 7);
        $this->signIn($viewer)->postJson("/api/finance/refunds/{$c['approved']}/sent", ['method' => 'gcash'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $editor = $this->financeStaff($vendor, ['refunds']);
        DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $editor->id, 'module' => 'refunds', 'permission' => 'edit', 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        $this->signIn($editor)->postJson("/api/finance/refunds/{$c['approved']}/sent", ['method' => 'gcash', 'reference' => 'GC-900', 'note' => 'Sent from the shop GCash'])->assertOk();
        $this->assertSame('GC-900', DB::table('order_requests')->where('id', $c['approved'])->value('refund_reference'));
        $this->assertSame('refund_sent', DB::table('order_request_messages')->where('order_request_id', $c['approved'])->value('kind'));

        $detail = $this->signIn($editor)->getJson("/api/finance/refunds/{$c['approved']}")->assertOk()->json('data');
        $this->assertSame('processing', $detail['finance_status']);
        $this->assertEquals(300, $detail['amount']);

        // Only an agreed refund can be sent
        $this->signIn($editor)->postJson("/api/finance/refunds/{$c['requested']}/sent", ['method' => 'gcash'])->assertStatus(422);
    }
}
