<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/** Procurement asks Finance for money for several products, paid by bank or e-wallet. */
class FundingRequestFormTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    private $vendor;
    private Employee $buyer;
    private Employee $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        Schema::dropIfExists('funding_request_items');
        Schema::dropIfExists('funding_requests');
        Schema::dropIfExists('products');
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('product_name');
            $t->string('sku')->nullable();
            $t->string('selling_type')->nullable();
            $t->integer('quantity_in_stock')->default(0);
            $t->string('supplier_name')->nullable();
            $t->softDeletes();
        });
        foreach (['2026_02_07_085208_create_funding_requests_table', '2026_03_10_065051_add_linked_order_id_to_funding_requests_table',
            '2026_04_02_120100_add_requester_and_approver_to_funding_requests_table', '2026_04_05_110000_add_payment_status_to_funding_requests_table',
            '2026_11_09_120000_add_items_and_payment_method_to_funding_requests', '2026_11_09_130000_add_approved_quantity_to_funding_request_items'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        // purchase_orders isn't built here; the request's link to it stays empty
        Schema::disableForeignKeyConstraints();

        $this->vendor = $this->shopOwner();
        $this->buyer = $this->staff(['view', 'create', 'edit']);
        $this->approver = $this->staff(['view', 'approve'], 'funding_requests');
    }

    private function staff(array $permissions, string $module = 'inventory_funding'): Employee
    {
        $e = Employee::forceCreate(['owner_id' => $this->vendor->id, 'name' => 'Staff ' . fake()->firstName(), 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'status' => 'Active']);
        foreach ($permissions as $p) {
            DB::table('employee_module_permissions')->insert([
                'owner_id' => $this->vendor->id, 'employee_id' => $e->id, 'module' => $module, 'permission' => $p,
                'access' => 'granular', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $e;
    }

    private function product(string $name, string $type = 'per_piece', int $stock = 5): int
    {
        return DB::table('products')->insertGetId(['owner_id' => $this->vendor->id, 'product_name' => $name, 'selling_type' => $type, 'quantity_in_stock' => $stock]);
    }

    private function form(array $items, array $extra = []): array
    {
        return array_merge([
            'approver_id' => $this->approver->id,
            'items' => $items,
            'required_delivery_date' => now()->addDays(3)->toDateString(),
            'urgency_level' => 'Urgent',
            'reason_for_shortage' => 'Seasonal Demand',
            'payment_method' => 'ewallet',
            'payment_channel' => 'GCash',
            'logistics_cost' => 150,
            'business_justification' => 'Valentine orders',
        ], $extra);
    }

    public function test_one_request_covers_several_products(): void
    {
        $rose = $this->product('Red rose');
        $tulip = $this->product('Tulip', 'per_bunch', 2);

        $res = $this->signIn($this->buyer)->postJson('/api/procurement/inventory/funding-requests', $this->form([
            ['product_id' => $rose, 'quantity' => 100, 'unit_cost' => 20],
            ['product_id' => $tulip, 'quantity' => 10, 'unit_cost' => 150],
        ]))->assertCreated();

        $id = $res->json('id');
        $row = DB::table('funding_requests')->find($id);
        $this->assertSame('Pending', $row->request_status);
        $this->assertSame('Red rose + 1 more', $row->product_name);
        $this->assertEquals(110, $row->requested_qty);
        $this->assertNull($row->uom); // stems and bunches mixed
        $this->assertEquals(3650, $row->estimated_total_cost); // 2000 + 1500 + 150 delivery
        $this->assertSame('ewallet', $row->payment_method);
        $this->assertSame('GCash', $row->payment_channel);
        $this->assertNull($row->tax_vat_estimate);

        $this->signIn($this->buyer)->getJson("/api/procurement/inventory/funding-requests/{$id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.1.uom', 'Bunches')
            ->assertJsonPath('data.items.1.current_stock', 2);
    }

    public function test_payment_must_be_a_listed_bank_or_e_wallet(): void
    {
        $rose = $this->product('Red rose');
        $item = [['product_id' => $rose, 'quantity' => 1, 'unit_cost' => 20]];

        $this->signIn($this->buyer)->postJson('/api/procurement/inventory/funding-requests', $this->form($item, ['payment_method' => 'bank', 'payment_channel' => 'GCash']))
            ->assertStatus(422)->assertJsonValidationErrors('payment_channel');
        $this->postJson('/api/procurement/inventory/funding-requests', $this->form($item, ['payment_method' => 'Cash']))
            ->assertStatus(422)->assertJsonValidationErrors('payment_method');
        $this->postJson('/api/procurement/inventory/funding-requests', $this->form($item, ['payment_method' => 'bank', 'payment_channel' => 'BDO']))
            ->assertCreated();
    }

    public function test_products_are_required_once_each_and_from_this_shop(): void
    {
        $rose = $this->product('Red rose');
        $other = DB::table('products')->insertGetId(['owner_id' => $this->vendor->id + 99, 'product_name' => 'Not ours']);

        $this->signIn($this->buyer)->postJson('/api/procurement/inventory/funding-requests', $this->form([]))
            ->assertStatus(422)->assertJsonValidationErrors('items');
        $this->postJson('/api/procurement/inventory/funding-requests', $this->form([
            ['product_id' => $rose, 'quantity' => 1, 'unit_cost' => 1],
            ['product_id' => $rose, 'quantity' => 2, 'unit_cost' => 1],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
        $this->postJson('/api/procurement/inventory/funding-requests', $this->form([
            ['product_id' => $other, 'quantity' => 1, 'unit_cost' => 1],
        ]))->assertStatus(422);
    }

    public function test_finance_approves_part_of_a_request_and_never_sees_drafts(): void
    {
        foreach (['vendor_balances', 'vendor_transactions'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('vendor_balances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->decimal('balance', 12, 2)->default(0);
            $t->decimal('total_earned', 12, 2)->default(0);
            $t->decimal('total_withdrawn', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('vendor_transactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->string('type');
            $t->string('category')->nullable();
            $t->decimal('amount', 12, 2);
            $t->decimal('balance_before', 12, 2)->default(0);
            $t->decimal('balance_after', 12, 2)->default(0);
            $t->string('description')->nullable();
            $t->string('status')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });

        $rose = $this->product('Red rose');
        $tulip = $this->product('Tulip');
        $id = $this->signIn($this->buyer)->postJson('/api/procurement/inventory/funding-requests', $this->form([
            ['product_id' => $rose, 'quantity' => 100, 'unit_cost' => 20],
            ['product_id' => $tulip, 'quantity' => 10, 'unit_cost' => 150],
        ]))->assertCreated()->json('id');
        $this->postJson('/api/procurement/inventory/funding-requests', ['is_draft' => true])->assertCreated();
        $lines = DB::table('funding_request_items')->where('funding_request_id', $id)->orderBy('id')->pluck('id');

        $this->app['auth']->forgetGuards();
        $this->signIn($this->approver)->getJson('/api/finance/funding-requests')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.items_count', 2);

        // More than was asked for is refused
        $this->postJson("/api/finance/funding-requests/{$id}/approve", [
            'approved_quantity' => 1, 'approved_amount' => 1,
            'items' => [['id' => $lines[0], 'approved_quantity' => 101]],
        ])->assertStatus(422);

        // Half the roses, no tulips
        $this->postJson("/api/finance/funding-requests/{$id}/approve", [
            'approved_quantity' => 50, 'approved_amount' => 1150,
            'items' => [['id' => $lines[0], 'approved_quantity' => 50], ['id' => $lines[1], 'approved_quantity' => 0]],
        ])->assertOk();

        $this->assertSame([50, 0], DB::table('funding_request_items')->where('funding_request_id', $id)->orderBy('id')->pluck('approved_quantity')->map(fn ($q) => (int) $q)->all());
        $row = DB::table('funding_requests')->find($id);
        $this->assertSame('Approved', $row->request_status);
        $this->assertEquals(50, $row->approved_quantity);
        $this->assertEquals(1150, $row->approved_amount);
    }

    public function test_a_half_filled_draft_is_edited_then_sent(): void
    {
        $rose = $this->product('Red rose');
        $lily = $this->product('Lily');

        $id = $this->signIn($this->buyer)->postJson('/api/procurement/inventory/funding-requests', [
            'is_draft' => true,
            'items' => [['product_id' => $rose, 'quantity' => 5, 'unit_cost' => 20]],
        ])->assertCreated()->json('id');
        $this->assertSame('Draft', DB::table('funding_requests')->where('id', $id)->value('request_status'));

        // Not ready to send yet
        $this->postJson("/api/procurement/inventory/funding-requests/{$id}/submit")->assertStatus(422);

        $this->putJson("/api/procurement/inventory/funding-requests/{$id}", $this->form([
            ['product_id' => $lily, 'quantity' => 4, 'unit_cost' => 50],
        ], ['submit' => true, 'logistics_cost' => 0]))->assertOk();

        $row = DB::table('funding_requests')->find($id);
        $this->assertSame('Pending', $row->request_status);
        $this->assertSame('Lily', $row->product_name);
        $this->assertEquals(200, $row->estimated_total_cost);
        $this->assertSame(['Lily'], DB::table('funding_request_items')->where('funding_request_id', $id)->pluck('product_name')->all());
    }
}
