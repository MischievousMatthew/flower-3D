<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\CreatesPurchasingTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Inventory & stock replenishment on the products' own stock count:
 * low and out of stock, what to order, adjustments and damage, materials.
 */
class InventoryReplenishmentTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, CreatesPurchasingTables, SignsInWithApiToken;

    private const BASE = '/api/procurement/inventory/stock';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        $this->createPurchasingTables();
        foreach (['warehouse_batch_logs', 'product_stock_adjustments'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('products', function (Blueprint $t) {
            $t->string('description')->nullable();
            $t->string('category')->nullable();
            $t->string('flower_type')->nullable();
            $t->string('color')->nullable();
            $t->decimal('selling_price', 10, 2)->default(0);
            $t->integer('min_stock_level')->default(0);
            $t->integer('max_stock_level')->nullable();
            $t->integer('supplier_lead_time')->nullable();
            $t->string('status')->default('active');
            $t->string('moderation_status', 20)->default('approved');
            $t->string('stock_kind', 20)->nullable();
            $t->string('stock_unit', 20)->nullable();
            $t->integer('preparation_days')->default(0);
        });
        Schema::create('product_stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('product_id');
            $t->integer('change');
            $t->integer('quantity_before');
            $t->integer('quantity_after');
            $t->string('reason', 30);
            $t->string('note', 500)->nullable();
            $t->string('actor_name')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('warehouse_batch_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('warehouse_batch_id');
            $t->unsignedBigInteger('performed_by')->nullable();
            $t->string('event_type');
            $t->integer('qty_change')->default(0);
            $t->integer('qty_after')->default(0);
            $t->string('condition_before')->nullable();
            $t->string('condition_after')->nullable();
            $t->string('from_location')->nullable();
            $t->string('to_location')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent();
        });
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function product(User $vendor, array $attrs): int
    {
        return DB::table('products')->insertGetId($attrs + ['owner_id' => $vendor->id, 'sku' => fake()->unique()->bothify('SKU-####'), 'quantity_in_stock' => 0]);
    }

    private function batch(User $vendor, int $product, int $qty, string $expires): int
    {
        return DB::table('warehouse_batches')->insertGetId(['owner_id' => $vendor->id, 'product_id' => $product, 'qty_received' => $qty, 'qty_remaining' => $qty, 'expiration_date' => $expires, 'received_date' => '2026-10-15', 'status' => 'active', 'condition_status' => 'fresh', 'batch_number' => "B-{$expires}"]);
    }

    public function test_it_finds_what_is_low_and_suggests_how_much_to_order(): void
    {
        $vendor = $this->shopOwner();
        // Roses: 8 on hand, minimum 20, target 100, 4 days to arrive, 30 sold in the last 30 days (1/day), 10 on order
        $rose = $this->product($vendor, ['product_name' => 'Red rose', 'category' => 'Roses', 'quantity_in_stock' => 8, 'min_stock_level' => 20, 'max_stock_level' => 100, 'supplier_lead_time' => 4]);
        $ribbon = $this->product($vendor, ['product_name' => 'Satin ribbon', 'category' => 'Ribbon', 'quantity_in_stock' => 0, 'min_stock_level' => 5]);
        $this->product($vendor, ['product_name' => 'Kraft paper', 'category' => 'Packaging', 'quantity_in_stock' => 50, 'min_stock_level' => 10]);

        $order = DB::table('orders')->insertGetId(['order_number' => 'A1', 'vendor_id' => $vendor->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cod', 'total_amount' => 100, 'created_at' => '2026-10-10 10:00:00', 'updated_at' => now()]);
        DB::table('order_items')->insert(['order_id' => $order, 'product_id' => $rose, 'quantity' => 30, 'created_at' => now(), 'updated_at' => now()]);

        $supplier = DB::table('suppliers')->insertGetId(['owner_id' => $vendor->id, 'company_name' => 'Bloom Farms', 'status' => 'active']);
        $po = DB::table('purchase_orders')->insertGetId(['owner_id' => $vendor->id, 'supplier_id' => $supplier, 'status' => 'shipped', 'order_number' => 'PO-1', 'created_at' => '2026-10-18 09:00:00']);
        DB::table('purchase_order_items')->insert(['purchase_order_id' => $po, 'product_id' => $rose, 'product_name' => 'Red rose', 'quantity' => 10, 'price' => 18.5, 'unit' => 'stem']);

        $d = $this->signIn($vendor)->getJson(self::BASE)->assertOk()->json('data');
        $byName = collect($d['items'])->keyBy('name');

        $r = $byName['Red rose'];
        $this->assertSame(['low', 10, 30, 8, 'flowers'], [$r['state'], $r['on_order'], $r['used_30d'], $r['days_left'], $r['kind']]);
        // Fill to 100, plus 4 days of use while it arrives, less 8 on hand and 10 coming
        $this->assertSame(86, $r['suggestion']['qty']);
        $this->assertSame('Bloom Farms', $r['last_supplier']['name']);
        $this->assertEquals(18.5, $r['last_supplier']['price']);

        $this->assertSame(['out', 'ribbons', 10], [$byName['Satin ribbon']['state'], $byName['Satin ribbon']['kind'], $byName['Satin ribbon']['suggestion']['qty']]);
        $this->assertSame(['ok', 'wrapping', 0], [$byName['Kraft paper']['state'], $byName['Kraft paper']['kind'], $byName['Kraft paper']['suggestion']['qty']]);
        $this->assertSame([1, 1, 2], [$d['counts']['out'], $d['counts']['low'], $d['counts']['reorder']]);
    }

    public function test_damage_comes_out_of_the_batches_that_expire_first(): void
    {
        $vendor = $this->shopOwner();
        $rose = $this->product($vendor, ['product_name' => 'Red rose', 'quantity_in_stock' => 50]);
        $old = $this->batch($vendor, $rose, 20, '2026-10-22');
        $new = $this->batch($vendor, $rose, 30, '2026-10-28');

        $this->signIn($vendor)->postJson(self::BASE . "/{$rose}/adjust", ['mode' => 'remove', 'quantity' => 25, 'reason' => 'damaged', 'note' => 'Crushed in the cooler'])
            ->assertOk()->assertJsonPath('data.product.quantity_in_stock', 25);

        $this->assertSame(0, (int) DB::table('warehouse_batches')->where('id', $old)->value('qty_remaining'));
        $this->assertSame('depleted', DB::table('warehouse_batches')->where('id', $old)->value('status'));
        $this->assertSame(25, (int) DB::table('warehouse_batches')->where('id', $new)->value('qty_remaining'));
        $this->assertSame(2, DB::table('warehouse_batch_logs')->where('event_type', 'CULLED')->count());

        // A cull on the warehouse floor counts as damage too, once
        DB::table('warehouse_batch_logs')->insert(['warehouse_batch_id' => $new, 'event_type' => 'CULLED', 'qty_change' => -5, 'qty_after' => 20, 'notes' => 'Botrytis', 'created_at' => now()]);
        $history = $this->getJson(self::BASE . '/history?filter=damaged')->assertOk()->json('data');
        $this->assertSame(['Damaged or wilted'], array_values(array_unique(array_column(array_filter($history, fn ($h) => $h['source'] === 'adjustment'), 'reason_label'))));
        $this->assertCount(1, array_filter($history, fn ($h) => $h['source'] === 'cull'));

        $rose30 = collect($this->getJson(self::BASE)->json('data.items'))->firstWhere('id', $rose);
        $this->assertSame(30, $rose30['damaged_30d']);

        // Damage can't be added, and stock can't go below zero
        $this->postJson(self::BASE . "/{$rose}/adjust", ['mode' => 'add', 'quantity' => 5, 'reason' => 'damaged'])->assertStatus(422);
        $this->postJson(self::BASE . "/{$rose}/adjust", ['mode' => 'remove', 'quantity' => 99, 'reason' => 'other'])
            ->assertStatus(422)->assertJsonPath('message', "Only 25 in stock, so you can't take away 99.");
    }

    public function test_a_count_above_the_batches_adds_a_batch_for_the_difference(): void
    {
        $vendor = $this->shopOwner();
        $rose = $this->product($vendor, ['product_name' => 'Red rose', 'sku' => 'RS', 'quantity_in_stock' => 20]);
        $this->batch($vendor, $rose, 20, '2026-10-25');

        $this->signIn($vendor)->postJson(self::BASE . "/{$rose}/adjust", ['mode' => 'set', 'quantity' => 26, 'reason' => 'count'])->assertOk();

        $this->assertSame(26, (int) DB::table('warehouse_batches')->where('product_id', $rose)->where('status', 'active')->sum('qty_remaining'));
        $this->assertSame(26, (int) DB::table('products')->where('id', $rose)->value('quantity_in_stock'));
        $this->assertSame(6, (int) DB::table('product_stock_adjustments')->value('change'));
    }

    public function test_dropping_below_the_minimum_alerts_the_owner_once(): void
    {
        $vendor = $this->shopOwner();
        $ribbon = $this->product($vendor, ['product_name' => 'Satin ribbon', 'quantity_in_stock' => 12, 'min_stock_level' => 10]);
        $this->signIn($vendor);

        $this->postJson(self::BASE . "/{$ribbon}/adjust", ['mode' => 'remove', 'quantity' => 1, 'reason' => 'used'])->assertOk();
        $this->assertSame(0, DB::table('user_notifications')->count());
        $this->postJson(self::BASE . "/{$ribbon}/adjust", ['mode' => 'remove', 'quantity' => 3, 'reason' => 'used'])->assertOk();
        $this->postJson(self::BASE . "/{$ribbon}/adjust", ['mode' => 'remove', 'quantity' => 2, 'reason' => 'used'])->assertOk();

        $alerts = DB::table('user_notifications')->where('user_id', $vendor->id)->get();
        $this->assertCount(1, $alerts);
        $this->assertSame('Running low: Satin ribbon', $alerts[0]->title);
        $this->assertSame('low_stock', $alerts[0]->type);
    }

    public function test_materials_are_counted_but_never_shown_to_customers(): void
    {
        $vendor = $this->shopOwner();
        $this->signIn($vendor);

        $id = $this->postJson(self::BASE . '/materials', [
            'name' => 'Clear vase, 20 cm', 'stock_kind' => 'vases', 'stock_unit' => 'piece', 'unit_cost' => 85, 'on_hand' => 12, 'min_stock_level' => 6, 'max_stock_level' => 24,
        ])->assertCreated()->assertJsonPath('data.quantity_in_stock', 12)->json('data.id');

        $vase = Product::findOrFail($id);
        $this->assertTrue($vase->isMaterial());
        $this->assertFalse($vase->isTakenDown());
        $this->assertSame(0, Product::listedForCustomers()->whereKey($id)->count());
        $this->assertSame(0, Product::forSale()->whereKey($id)->count());

        $item = collect($this->getJson(self::BASE)->json('data.items'))->firstWhere('id', $id);
        $this->assertSame(['vases', true, 'piece', 12], [$item['kind'], $item['material'], $item['unit'], $item['on_hand']]);

        // Flowers are added as products, not as materials
        $this->postJson(self::BASE . '/materials', ['name' => 'Rose', 'stock_kind' => 'flowers'])->assertStatus(422);
    }

    public function test_levels_need_the_edit_permission_and_a_sensible_target(): void
    {
        $vendor = $this->shopOwner();
        $rose = $this->product($vendor, ['product_name' => 'Red rose', 'quantity_in_stock' => 10]);
        $viewer = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Sam', 'email' => fake()->unique()->safeEmail(), 'department' => 'Procurement', 'password' => 'x', 'status' => 'Active']);
        DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $viewer->id, 'module' => 'inventory_products', 'permission' => 'view', 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);

        $this->signIn($viewer)->getJson(self::BASE)->assertOk();
        $this->patchJson(self::BASE . "/{$rose}/levels", ['min_stock_level' => 20])->assertForbidden();

        $this->signIn($vendor)->patchJson(self::BASE . "/{$rose}/levels", ['min_stock_level' => 20, 'max_stock_level' => 10])->assertStatus(422);
        $this->patchJson(self::BASE . "/{$rose}/levels", ['min_stock_level' => 20, 'max_stock_level' => 80, 'stock_kind' => 'flowers', 'supplier_lead_time' => 3])
            ->assertOk()->assertJsonPath('data.max_stock_level', 80);
    }
}
