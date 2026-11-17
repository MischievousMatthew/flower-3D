<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Procurement asks for 100 roses; Finance can fund 75, so it goes back to
 * Procurement, who asks for 80; it goes back to Finance, and so on until
 * they agree.
 */
class FundingCounterOfferTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    private $vendor;
    private Employee $finance;
    private Employee $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        foreach (['funding_request_rounds', 'funding_payment_checkouts', 'funding_request_payments', 'funding_request_items', 'funding_requests'] as $t) {
            Schema::dropIfExists($t);
        }
        foreach (['2026_02_07_085208_create_funding_requests_table', '2026_03_10_065051_add_linked_order_id_to_funding_requests_table',
            '2026_04_02_120100_add_requester_and_approver_to_funding_requests_table', '2026_04_05_110000_add_payment_status_to_funding_requests_table',
            '2026_11_09_120000_add_items_and_payment_method_to_funding_requests', '2026_11_09_130000_add_approved_quantity_to_funding_request_items',
            '2026_11_09_140000_funding_request_payments', '2026_11_17_100000_funding_request_counter_offers'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::disableForeignKeyConstraints();

        $this->vendor = $this->shopOwner();
        $this->finance = $this->staff('Fe', 'funding_requests', ['view', 'approve', 'reject']);
        $this->buyer = $this->staff('Bo', 'inventory_funding', ['view', 'create', 'edit']);
    }

    private function staff(string $name, string $module, array $perms): Employee
    {
        $e = Employee::forceCreate(['owner_id' => $this->vendor->id, 'name' => $name, 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'status' => 'Active']);
        foreach ($perms as $p) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $this->vendor->id, 'employee_id' => $e->id, 'module' => $module, 'permission' => $p, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $e;
    }

    /** 100 red roses at ₱20 plus a ₱100 delivery fee (₱2,100) */
    private function request(): array
    {
        $id = DB::table('funding_requests')->insertGetId([
            'owner_id' => $this->vendor->id, 'finance_request_id' => 'FR-' . fake()->unique()->numberBetween(1, 99999),
            'submitted_by_employee_id' => $this->buyer->id, 'requester_id' => $this->buyer->id, 'approver_id' => $this->finance->id,
            'request_date' => now()->toDateString(), 'request_status' => 'Pending', 'payment_status' => 'unpaid',
            'product_name' => 'Red rose', 'requested_qty' => 100, 'estimated_total_cost' => 2100, 'logistics_cost' => 100,
            'payment_method' => 'bank', 'payment_channel' => 'BDO', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = DB::table('funding_request_items')->insertGetId(['funding_request_id' => $id, 'product_name' => 'Red rose', 'uom' => 'Stems', 'quantity' => 100, 'unit_cost' => 20, 'line_total' => 2000, 'created_at' => now(), 'updated_at' => now()]);

        return [$id, $item];
    }

    private function financeOffers(int $id, int $item, int $qty, float $amount)
    {
        $this->app['auth']->forgetGuards();

        return $this->signIn($this->finance)->postJson("/api/finance/funding-requests/{$id}/approve", [
            'approved_quantity' => $qty, 'approved_amount' => $amount, 'finance_remarks' => 'Cash is tight',
            'items' => [['id' => $item, 'approved_quantity' => $qty]],
        ]);
    }

    private function buyerAnswers(int $id, array $body)
    {
        $this->app['auth']->forgetGuards();

        return $this->signIn($this->buyer)->postJson("/api/procurement/inventory/funding-requests/{$id}/respond", $body);
    }

    public function test_they_go_back_and_forth_until_they_agree(): void
    {
        [$id, $item] = $this->request();

        // Finance can only buy 75: back to Procurement, not approved
        $this->financeOffers($id, $item, 75, 1600)->assertOk();
        $this->assertSame('Countered', DB::table('funding_requests')->find($id)->request_status);
        $this->assertSame(75, (int) DB::table('funding_request_items')->find($item)->offered_quantity);
        $this->assertEquals(1600, DB::table('funding_requests')->find($id)->offered_amount);

        // Procurement needs at least 80: back to Finance, priced for 80
        $this->buyerAnswers($id, ['action' => 'counter', 'items' => [['id' => $item, 'quantity' => 80]], 'note' => 'Need 80 for the wedding'])->assertOk();
        $row = DB::table('funding_requests')->find($id);
        $this->assertSame('Pending', $row->request_status);
        $this->assertEquals(1700, $row->estimated_total_cost);
        $this->assertSame(80, (int) DB::table('funding_request_items')->find($item)->quantity);

        // Finance can't approve more than the new ask
        $this->financeOffers($id, $item, 81, 1720)->assertStatus(422);

        // Finance agrees to 80
        $this->financeOffers($id, $item, 80, 1700)->assertOk();
        $row = DB::table('funding_requests')->find($id);
        $this->assertSame('Approved', $row->request_status);
        $this->assertEquals(1700, $row->approved_amount);
        $this->assertSame(80, (int) DB::table('funding_request_items')->find($item)->approved_quantity);

        // Every turn is on the record, and both sides see it
        $this->assertSame(['finance', 'procurement', 'approved'], DB::table('funding_request_rounds')->where('funding_request_id', $id)->orderBy('id')->pluck('kind')->all());
        $this->buyerAnswers($id, ['action' => 'accept'])->assertStatus(409);
        $this->app['auth']->forgetGuards();
        $this->signIn($this->buyer)->getJson("/api/procurement/inventory/funding-requests/{$id}")->assertOk()->assertJsonCount(3, 'data.rounds');
    }

    public function test_procurement_can_take_the_offer(): void
    {
        [$id, $item] = $this->request();
        $this->financeOffers($id, $item, 75, 1600)->assertOk();

        $this->buyerAnswers($id, ['action' => 'accept'])->assertOk();
        $row = DB::table('funding_requests')->find($id);
        $this->assertSame('Approved', $row->request_status);
        $this->assertEquals(1600, $row->approved_amount);
        $this->assertEquals(75, $row->approved_quantity);
        $this->assertSame(75, (int) DB::table('funding_request_items')->find($item)->approved_quantity);
    }

    public function test_a_new_ask_stays_between_the_offer_and_the_last_ask(): void
    {
        [$id, $item] = $this->request();
        $this->financeOffers($id, $item, 75, 1600)->assertOk();

        $this->buyerAnswers($id, ['action' => 'counter', 'items' => [['id' => $item, 'quantity' => 70]]])->assertStatus(422);
        $this->buyerAnswers($id, ['action' => 'counter', 'items' => [['id' => $item, 'quantity' => 120]]])->assertStatus(422);
        // Asking for exactly the offer is just taking it
        $this->buyerAnswers($id, ['action' => 'counter', 'items' => [['id' => $item, 'quantity' => 75]]])->assertStatus(422);
        $this->assertSame('Countered', DB::table('funding_requests')->find($id)->request_status);
    }

    public function test_approving_everything_asked_is_still_final(): void
    {
        [$id, $item] = $this->request();
        $this->financeOffers($id, $item, 100, 2100)->assertOk();
        $this->assertSame('Approved', DB::table('funding_requests')->find($id)->request_status);
        $this->assertSame(0, DB::table('funding_request_rounds')->count());
    }
}
