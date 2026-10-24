<?php

namespace Tests\Feature;

use App\Models\PayMongoWebhookEvent;
use App\Models\User;
use App\Support\PayMongoPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Finance → Payments: PayMongo and cash-on-delivery payments with their status
 * and fees, reconciliation against the books, and recording the cash on
 * delivery the shop received and settled.
 */
class PaymentsTest extends TestCase
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

    /** A PayMongo webhook body for a paid checkout session (amounts in centavos) */
    private function paidEvent(int $amount, int $fee, string $source): array
    {
        return ['data' => ['id' => 'evt_' . $amount, 'attributes' => ['type' => 'checkout_session.payment.paid', 'data' => [
            'id' => 'cs_' . $amount,
            'attributes' => ['payments' => [['id' => 'pay_' . $amount, 'attributes' => [
                'amount' => $amount, 'fee' => $fee, 'net_amount' => $amount - $fee, 'status' => 'paid', 'source' => ['type' => $source],
            ]]]],
        ]]]];
    }

    private function shop(): array
    {
        $vendor = $this->shopOwner();
        $o = [];
        // PayMongo: paid with the fee saved on the order
        $o['gcash'] = $this->sale($vendor, 1000, 0, '2026-10-05 12:00:00', ['payment_method' => 'ewallet', 'paymongo_response' => json_encode(['reference_number' => 'REF-A', 'charged_amount' => 1000, 'fee' => 25, 'net_amount' => 975, 'source' => 'gcash'])]);
        // An older payment: the fee is only in PayMongo's webhook log
        $o['maya'] = $this->sale($vendor, 500, 0, '2026-10-06 12:00:00', ['payment_method' => 'ewallet']);
        PayMongoWebhookEvent::create(['event_id' => 'evt_1', 'event_type' => 'checkout_session.payment.paid', 'order_id' => $o['maya'], 'payload' => $this->paidEvent(50000, 1250, 'paymaya'), 'status' => 'processed']);
        $o['pending'] = $this->order($vendor, ['payment_method' => 'ewallet', 'payment_status' => 'unpaid', 'status' => 'pending', 'total_amount' => 450, 'created_at' => '2026-10-20 08:00:00']);
        $o['expired'] = $this->order($vendor, ['payment_method' => 'ewallet', 'payment_status' => 'unpaid', 'status' => 'pending', 'total_amount' => 350, 'created_at' => '2026-10-10 08:00:00']);
        $o['failed'] = $this->order($vendor, ['payment_method' => 'ewallet', 'payment_status' => 'failed', 'status' => 'payment_failed', 'total_amount' => 250, 'created_at' => '2026-10-11 08:00:00']);
        // Paid, but never credited in the books
        $o['unbooked'] = $this->order($vendor, ['payment_method' => 'ewallet', 'payment_status' => 'paid', 'status' => 'processing', 'total_amount' => 800, 'created_at' => '2026-10-12 08:00:00', 'paid_at' => '2026-10-12 08:05:00']);
        // Cash on delivery at each stage
        $o['cod_new'] = $this->order($vendor, ['payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'processing', 'total_amount' => 600, 'created_at' => '2026-10-15 08:00:00']);
        $o['cod_delivered'] = $this->order($vendor, ['payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'delivered', 'total_amount' => 400, 'created_at' => '2026-10-14 08:00:00']);
        $o['cod_done'] = $this->sale($vendor, 700, 0, '2026-10-13 12:00:00', ['payment_method' => 'cod']);
        $o['cod_cancelled'] = $this->order($vendor, ['payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'cancelled', 'total_amount' => 300, 'created_at' => '2026-10-09 08:00:00']);

        return [$vendor, $o];
    }

    public function test_payments_have_a_status_and_paymongo_fees_where_known(): void
    {
        [$vendor, $o] = $this->shop();

        $data = $this->signIn($vendor)->getJson('/api/vendor/books/payments' . self::OCTOBER)->assertOk()->json('data');
        $pm = $data['summary']['paymongo'];

        $this->assertSame(3, $pm['statuses']['paid']['count']);
        $this->assertSame(1, $pm['statuses']['pending']['count']);
        $this->assertSame(1, $pm['statuses']['expired']['count']);
        $this->assertSame(1, $pm['statuses']['failed']['count']);
        // ₱25 saved on the order + ₱12.50 read from the webhook log
        $this->assertEquals(37.5, $pm['fees']);
        $this->assertSame(2, $pm['fees_known']);
        $this->assertEquals(975 + 487.5 + 800, $pm['net_received']);
        // 3 paid out of 5 that finished (pending ones don't count)
        $this->assertEquals(60, $pm['success_rate']);
        $this->assertSame(['gcash', 'ewallet', 'paymaya'], array_column($pm['providers'], 'provider'));

        $rows = collect($data['rows'])->keyBy('id');
        $this->assertEquals(12.5, $rows[$o['maya']]['fee']);
        $this->assertSame('paymaya', $rows[$o['maya']]['provider']);
        $this->assertSame('REF-A', $rows[$o['gcash']]['reference']);
        $this->assertSame('cancelled', $rows[$o['cod_cancelled']]['status']);
    }

    public function test_reconciliation_flags_payments_that_dont_match_the_books(): void
    {
        [$vendor, $o] = $this->shop();

        $rec = $this->signIn($vendor)->getJson('/api/vendor/books/payments' . self::OCTOBER)->assertOk()->json('data.reconciliation');

        $this->assertSame(4, $rec['checked']);
        $this->assertSame(3, $rec['matched']);
        $this->assertSame($o['unbooked'], $rec['issues'][0]['id']);
        $this->assertSame('missing_in_books', $rec['issues'][0]['issues'][0]['code']);
    }

    public function test_cash_on_delivery_is_collected_settled_and_can_be_undone(): void
    {
        [$vendor, $o] = $this->shop();
        $api = $this->signIn($vendor);

        $stages = $api->getJson('/api/vendor/books/payments' . self::OCTOBER)->json('data.summary.cod.stages');
        $this->assertSame(1, $stages['awaiting_delivery']['count']);
        $this->assertSame(2, $stages['to_collect']['count']);
        $this->assertSame(1, $stages['void']['count']);

        // ₱650 handed over for a ₱700 order: short by ₱50
        $row = $api->postJson("/api/vendor/books/payments/cod/{$o['cod_done']}/collect", ['amount' => 650, 'collected_by' => 'Rider Jun'])
            ->assertOk()->json('data');
        $this->assertSame('collected', $row['cod']['stage']);
        $this->assertEquals(-50, $row['cod']['difference']);
        $this->assertSame('cod_short', $row['issues'][0]['code']);

        $api->postJson('/api/vendor/books/payments/cod/settle', ['order_ids' => [$o['cod_done']], 'reference' => 'DEP-1'])
            ->assertOk()->assertJsonPath('data.settled', 1);
        $this->assertSame('DEP-1', DB::table('cod_collections')->where('order_id', $o['cod_done'])->value('settlement_reference'));
        $this->assertSame(1, $api->getJson('/api/vendor/books/payments' . self::OCTOBER . '&cod_stage=settled')->json('data.meta.total'));

        // Undo takes back the settlement first, then the collection
        $this->assertSame('collected', $api->deleteJson("/api/vendor/books/payments/cod/{$o['cod_done']}")->assertOk()->json('data.cod.stage'));
        $this->assertSame('to_collect', $api->deleteJson("/api/vendor/books/payments/cod/{$o['cod_done']}")->assertOk()->json('data.cod.stage'));

        // No cash for cancelled or online orders, and nothing to settle before it's received
        $api->postJson("/api/vendor/books/payments/cod/{$o['cod_cancelled']}/collect", ['amount' => 300])->assertStatus(422);
        $api->postJson("/api/vendor/books/payments/cod/{$o['gcash']}/collect", ['amount' => 1000])->assertStatus(422);
        $api->postJson('/api/vendor/books/payments/cod/settle', ['order_ids' => [$o['cod_delivered']]])->assertStatus(422);
    }

    public function test_the_list_filters_by_method_status_stage_issues_and_search(): void
    {
        [$vendor, $o] = $this->shop();
        $api = $this->signIn($vendor);
        $total = fn (string $q) => $api->getJson('/api/vendor/books/payments' . self::OCTOBER . $q)->json('data.meta.total');

        $this->assertSame(10, $total(''));
        $this->assertSame(4, $total('&method=cod'));
        $this->assertSame(1, $total('&status=expired'));
        $this->assertSame(2, $total('&cod_stage=to_collect'));
        $this->assertSame(1, $total('&issues=1'));
        $this->assertSame(1, $total('&search=ref-a'));
    }

    public function test_finance_staff_need_view_to_look_and_edit_to_record_cash(): void
    {
        [$vendor, $o] = $this->shop();

        $this->signIn($this->financeStaff($vendor))->getJson('/api/finance/payments')->assertForbidden();

        $viewer = $this->financeStaff($vendor, ['payments']);
        $this->signIn($viewer)->getJson('/api/finance/payments' . self::OCTOBER)->assertOk()->assertJsonPath('data.meta.total', 10);
        $this->signIn($viewer)->postJson("/api/finance/payments/cod/{$o['cod_delivered']}/collect", ['amount' => 400])->assertForbidden();

        $editor = $this->financeStaff($vendor, ['payments']);
        DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $editor->id, 'module' => 'payments', 'permission' => 'edit', 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        $this->signIn($editor)->postJson("/api/finance/payments/cod/{$o['cod_delivered']}/collect", ['amount' => 400])
            ->assertOk()->assertJsonPath('data.cod.stage', 'collected');
        $this->assertSame($editor->name, DB::table('cod_collections')->where('order_id', $o['cod_delivered'])->value('recorded_by'));
    }

    public function test_paymongo_payment_details_are_read_from_webhooks_and_the_event_log_table_is_right(): void
    {
        $this->assertSame(
            ['payment_id' => 'pay_50000', 'charged_amount' => 500.0, 'fee' => 12.5, 'net_amount' => 487.5, 'source' => 'paymaya'],
            PayMongoPayment::fromEvent($this->paidEvent(50000, 1250, 'paymaya')),
        );
        // The webhook log is stored where the migration created it
        $this->assertSame('paymongo_webhook_events', (new PayMongoWebhookEvent())->getTable());
    }
}
