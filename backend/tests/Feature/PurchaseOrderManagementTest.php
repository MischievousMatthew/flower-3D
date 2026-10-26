<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\CreatesPurchasingTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Purchase orders: Draft → Pending approval → Ordered → Partially received →
 * Completed, with dates, emailing the supplier, and the order's history.
 */
class PurchaseOrderManagementTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, CreatesPurchasingTables, SignsInWithApiToken;

    private const BASE = '/api/procurement/supply-chain/orders';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        $this->createPurchasingTables();
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'x'], 201)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function supplier(User $vendor, array $extra = []): int
    {
        return DB::table('suppliers')->insertGetId($extra + ['owner_id' => $vendor->id, 'company_name' => 'Bloom Farms', 'contact_person' => 'Ana', 'email' => 'orders@bloom.ph']);
    }

    private function product(User $vendor, string $name, string $sku): int
    {
        return DB::table('products')->insertGetId(['owner_id' => $vendor->id, 'product_name' => $name, 'sku' => $sku, 'quantity_in_stock' => 0]);
    }

    private function staff(User $vendor, array $permissions): Employee
    {
        $e = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Mark', 'email' => fake()->unique()->safeEmail(), 'department' => 'Procurement', 'password' => 'x', 'status' => 'Active']);
        foreach ($permissions as $p) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $e->id, 'module' => 'sc_orders', 'permission' => $p, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $e;
    }

    private function newOrder(User $vendor, int $supplier, string $action, array $extra = []): array
    {
        return $extra + [
            'supplier_id' => $supplier, 'action' => $action, 'order_date' => '2026-10-20', 'expected_date' => '2026-10-24', 'notes' => 'Deliver before 9 AM',
            'items' => [
                ['product_name' => 'Red rose', 'quantity' => 100, 'price' => 18.5, 'unit' => 'stem'],
                ['product_name' => 'Eucalyptus', 'quantity' => 10, 'price' => 150, 'unit' => 'bunch'],
            ],
        ];
    }

    public function test_a_draft_goes_for_approval_then_to_the_supplier(): void
    {
        $vendor = $this->shopOwner();
        $supplier = $this->supplier($vendor);
        $buyer = $this->staff($vendor, ['view', 'create', 'edit']);

        // Staff without Approve can draft and ask for approval, not approve
        $this->signIn($buyer)->postJson(self::BASE, $this->newOrder($vendor, $supplier, 'approve'))->assertForbidden();
        $id = $this->postJson(self::BASE, $this->newOrder($vendor, $supplier, 'draft'))->assertCreated()->assertJsonPath('status', 'draft')->json('id');

        $order = PurchaseOrder::findOrFail($id);
        $this->assertEquals(3350, $order->total_amount);
        $this->assertSame('2026-10-24', $order->expected_date->toDateString());
        $this->assertSame('Mark', $order->created_by_name);

        // Drafts can be changed
        $this->putJson(self::BASE . "/{$id}", ['expected_date' => '2026-10-25', 'items' => [['product_name' => 'Red rose', 'quantity' => 120, 'price' => 18.5, 'unit' => 'stem']]])->assertOk();
        $this->assertEquals(2220, $order->fresh()->total_amount);

        $this->postJson(self::BASE . "/{$id}/submit")->assertOk()->assertJsonPath('status', 'pending_approval');
        $this->postJson(self::BASE . "/{$id}/approve")->assertForbidden();

        // The owner sends it back once, then approves it and it's emailed to the supplier
        $this->signIn($vendor)->postJson(self::BASE . "/{$id}/send-back", ['reason' => 'Ask for 150 roses'])->assertOk()->assertJsonPath('status', 'draft');
        $this->signIn($buyer)->postJson(self::BASE . "/{$id}/submit")->assertOk();
        $res = $this->signIn($vendor)->postJson(self::BASE . "/{$id}/approve", ['send_email' => true])->assertOk();
        $res->assertJsonPath('status', 'pending')->assertJsonPath('emailed', true);

        $order->refresh();
        $this->assertNotNull($order->approved_at);
        $this->assertSame('orders@bloom.ph', $order->sent_to_email);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'brevo') && str_contains($r['subject'], $order->order_number));

        // Sent orders can't be changed or deleted
        $this->putJson(self::BASE . "/{$id}", ['notes' => 'x'])->assertStatus(422);
        $this->deleteJson(self::BASE . "/{$id}")->assertStatus(422);

        // Everything is in the history
        $events = DB::table('purchase_order_events')->where('purchase_order_id', $id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['created', 'edited', 'status', 'sent_back', 'status', 'approved', 'emailed'], $events);
        $this->assertSame('Ask for 150 roses', DB::table('purchase_order_events')->where('event', 'sent_back')->value('note'));
    }

    public function test_receiving_part_then_the_rest(): void
    {
        $vendor = $this->shopOwner();
        $supplier = $this->supplier($vendor);
        $rose = $this->product($vendor, 'Red rose', 'RS');
        $this->product($vendor, 'Eucalyptus', 'EU');
        $this->signIn($vendor);

        $id = $this->postJson(self::BASE, $this->newOrder($vendor, $supplier, 'approve'))->assertCreated()->json('id');
        $items = PurchaseOrder::findOrFail($id)->items;
        [$roses, $euc] = [$items->firstWhere('product_name', 'Red rose'), $items->firstWhere('product_name', 'Eucalyptus')];

        // 60 roses arrive
        $this->postJson(self::BASE . "/{$id}/receive", ['items' => [['id' => $roses->id, 'qty' => 60], ['id' => $euc->id, 'qty' => 0]]])
            ->assertOk()->assertJsonPath('status', 'partially_received');
        $this->assertSame(60, DB::table('products')->where('id', $rose)->value('quantity_in_stock'));
        $this->assertSame(1, DB::table('warehouse_batches')->count());

        // More than is still to come is refused
        $this->postJson(self::BASE . "/{$id}/receive", ['items' => [['id' => $roses->id, 'qty' => 41]]])
            ->assertStatus(422)->assertJsonPath('message', 'Only 40 of Red rose are still to come.');

        // The rest arrives
        $this->postJson(self::BASE . "/{$id}/receive", ['items' => [['id' => $roses->id, 'qty' => 40], ['id' => $euc->id, 'qty' => 10]]])
            ->assertOk()->assertJsonPath('status', 'completed');
        $this->assertSame(100, DB::table('products')->where('id', $rose)->value('quantity_in_stock'));
        $this->assertNotNull(PurchaseOrder::find($id)->completed_at);

        // Completing through the old status step adds nothing twice
        $this->assertSame(3, DB::table('warehouse_batches')->count());
    }

    public function test_completing_the_old_way_only_receives_what_is_left(): void
    {
        $vendor = $this->shopOwner();
        $supplier = $this->supplier($vendor);
        $rose = $this->product($vendor, 'Red rose', 'RS');
        $this->product($vendor, 'Eucalyptus', 'EU');
        $this->signIn($vendor);

        $id = $this->postJson(self::BASE, $this->newOrder($vendor, $supplier, 'approve'))->json('id');
        $roses = PurchaseOrder::findOrFail($id)->items->firstWhere('product_name', 'Red rose');
        $this->postJson(self::BASE . "/{$id}/receive", ['items' => [['id' => $roses->id, 'qty' => 30]]])->assertOk();

        $this->patchJson(self::BASE . "/{$id}/status", ['status' => 'completed'])->assertOk();
        $this->assertSame(100, DB::table('products')->where('id', $rose)->value('quantity_in_stock'));
    }

    public function test_a_warehouse_batch_for_one_line_only_part_receives_the_order(): void
    {
        $vendor = $this->shopOwner();
        $supplier = $this->supplier($vendor);
        $rose = $this->product($vendor, 'Red rose', 'RS');
        $euc = $this->product($vendor, 'Eucalyptus', 'EU');
        $this->signIn($vendor);

        $id = $this->postJson(self::BASE, $this->newOrder($vendor, $supplier, 'approve'))->json('id');
        $service = app(OrderService::class);

        $service->recordBatchReceipt(PurchaseOrder::find($id), $rose, 100);
        $this->assertSame('partially_received', PurchaseOrder::find($id)->status);
        $service->recordBatchReceipt(PurchaseOrder::find($id), $euc, 10);
        $this->assertSame('completed', PurchaseOrder::find($id)->status);
    }

    public function test_the_list_filters_by_stage_and_counts_every_stage(): void
    {
        $vendor = $this->shopOwner();
        $supplier = $this->supplier($vendor);
        $this->signIn($vendor);

        $this->postJson(self::BASE, $this->newOrder($vendor, $supplier, 'draft'))->assertCreated();
        $this->postJson(self::BASE, $this->newOrder($vendor, $supplier, 'submit'))->assertCreated();
        // Due yesterday and still not here: late
        $this->postJson(self::BASE, $this->newOrder($vendor, $supplier, 'approve', ['order_date' => '2026-10-15', 'expected_date' => '2026-10-19']))->assertCreated();
        // The old way (no action) still goes straight to the supplier
        $this->postJson(self::BASE, ['supplier_id' => $supplier, 'items' => [['product_name' => 'Tulip', 'quantity' => 5, 'price' => 40]]])
            ->assertCreated()->assertJsonPath('status', 'pending');

        $res = $this->getJson(self::BASE . '?stage=ordered')->assertOk();
        $this->assertCount(2, $res->json('data'));
        $this->assertSame(['draft' => 1, 'pending_approval' => 1, 'ordered' => 2, 'partially_received' => 0, 'completed' => 0], $res->json('counts.stages'));
        $this->assertSame(1, $res->json('counts.overdue'));
        $this->assertCount(1, $this->getJson(self::BASE . '?overdue=1')->json('data'));
        $this->assertCount(1, $this->getJson(self::BASE . '?search=tulip')->json('data'));
    }

    public function test_drafts_can_be_deleted_and_unlink_their_funding_request(): void
    {
        $vendor = $this->shopOwner();
        $supplier = $this->supplier($vendor);
        $this->signIn($vendor);

        $id = $this->postJson(self::BASE, $this->newOrder($vendor, $supplier, 'draft'))->json('id');
        DB::table('funding_requests')->insert(['owner_id' => $vendor->id, 'linked_order_id' => $id]);

        $this->deleteJson(self::BASE . "/{$id}")->assertOk();
        $this->assertNull(PurchaseOrder::find($id));
        $this->assertNull(DB::table('funding_requests')->value('linked_order_id'));
        $this->assertSame(0, DB::table('purchase_order_items')->count());
    }

    public function test_emailing_needs_an_approved_order_and_a_supplier_email(): void
    {
        $vendor = $this->shopOwner();
        $noEmail = $this->supplier($vendor, ['email' => null, 'company_name' => 'Dangwa Stall 4']);
        $this->signIn($vendor);

        $draft = $this->postJson(self::BASE, $this->newOrder($vendor, $noEmail, 'draft'))->json('id');
        $this->postJson(self::BASE . "/{$draft}/email")->assertStatus(422);

        $sent = $this->postJson(self::BASE, $this->newOrder($vendor, $noEmail, 'approve', ['send_email' => true]))->assertCreated()->assertJsonPath('emailed', null)->json('id');
        $this->postJson(self::BASE . "/{$sent}/email")->assertStatus(422)
            ->assertJsonPath('message', "Dangwa Stall 4 has no email address. Add one on the supplier's details, or print the order instead.");
    }
}
