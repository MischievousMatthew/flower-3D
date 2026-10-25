<?php

namespace Tests\Feature;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Supply Chain dashboard: purchase orders and what's still on the way,
 * low and empty stock, supplier track records, wastage, and stock on the
 * floor that is past its date.
 */
class SupplyChainDashboardTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        foreach (['warehouse_batch_logs', 'warehouse_batches', 'warehouse_locations', 'shipments', 'purchase_order_items', 'purchase_orders', 'suppliers'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('product_name')->nullable();
            $t->string('sku')->nullable();
            $t->string('category')->nullable();
            $t->integer('quantity_in_stock')->default(0);
            $t->integer('min_stock_level')->nullable();
            $t->softDeletes();
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('company_name');
            $t->string('logo')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->string('order_number')->nullable();
            $t->string('status')->default('pending');
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('purchase_order_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('product_name')->nullable();
            $t->integer('quantity');
            $t->decimal('price', 12, 2)->default(0);
            $t->decimal('subtotal', 12, 2)->default(0);
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('purchase_order_id');
            $t->string('tracking_number')->nullable();
            $t->string('carrier')->nullable();
            $t->date('shipped_date')->nullable();
            $t->date('received_date')->nullable();
            $t->string('status')->default('pending');
        });
        Schema::create('warehouse_locations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('warehouse_id')->nullable();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('warehouse_batches', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('warehouse_location_id')->nullable();
            $t->string('storage_location')->nullable();
            $t->string('batch_number')->nullable();
            $t->date('expiration_date')->nullable();
            $t->unsignedInteger('qty_received')->default(0);
            $t->unsignedInteger('qty_remaining')->default(0);
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('warehouse_batch_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('warehouse_batch_id');
            $t->string('event_type');
            $t->integer('qty_change')->default(0);
            $t->integer('qty_after')->default(0);
            $t->timestamp('created_at')->nullable();
        });
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function po(int $owner, int $supplier, string $status, float $amount, string $at): int
    {
        return DB::table('purchase_orders')->insertGetId([
            'owner_id' => $owner, 'supplier_id' => $supplier, 'status' => $status, 'total_amount' => $amount, 'created_at' => $at,
        ]);
    }

    public function test_it_sums_up_orders_deliveries_stock_suppliers_and_waste(): void
    {
        $vendor = $this->shopOwner();
        $bloom = DB::table('suppliers')->insertGetId(['owner_id' => $vendor->id, 'company_name' => 'Bloom Farms']);
        $petal = DB::table('suppliers')->insertGetId(['owner_id' => $vendor->id, 'company_name' => 'Petal Co']);

        // Bloom: one done (ordered Oct 1, arrived Oct 4), one on the road. Petal: one being prepared, one waiting
        $done = $this->po($vendor->id, $bloom, 'completed', 5000, '2026-10-01 09:00:00');
        $road = $this->po($vendor->id, $bloom, 'shipped', 3000, '2026-10-15 09:00:00');
        $this->po($vendor->id, $petal, 'processing', 1200, '2026-10-18 09:00:00');
        $this->po($vendor->id, $petal, 'pending', 800, '2026-10-19 09:00:00');
        DB::table('shipments')->insert([
            ['purchase_order_id' => $done, 'status' => 'delivered', 'shipped_date' => '2026-10-02', 'received_date' => '2026-10-04', 'carrier' => 'LBC', 'tracking_number' => 'A1'],
            ['purchase_order_id' => $road, 'status' => 'in_transit', 'shipped_date' => '2026-10-17', 'received_date' => null, 'carrier' => 'J&T', 'tracking_number' => 'B2'],
        ]);
        DB::table('purchase_order_items')->insert(['purchase_order_id' => $road, 'quantity' => 120, 'product_name' => 'Rose']);

        // Another shop's order stays out
        $other = $this->shopOwner();
        $theirs = DB::table('suppliers')->insertGetId(['owner_id' => $other->id, 'company_name' => 'Elsewhere']);
        $this->po($other->id, $theirs, 'shipped', 9999, '2026-10-16 09:00:00');

        // Stock: rose is gone, tulip is under its minimum of 20, lily is fine (default minimum 10)
        $rose = DB::table('products')->insertGetId(['owner_id' => $vendor->id, 'product_name' => 'Rose', 'quantity_in_stock' => 0, 'min_stock_level' => 15, 'purchase_price' => 25]);
        DB::table('products')->insert(['owner_id' => $vendor->id, 'product_name' => 'Tulip', 'quantity_in_stock' => 6, 'min_stock_level' => 20]);
        DB::table('products')->insert(['owner_id' => $vendor->id, 'product_name' => 'Lily', 'quantity_in_stock' => 40]);

        // Floor: one rose batch two days past its date, one expiring tomorrow
        $cooler = DB::table('warehouse_locations')->insertGetId(['name' => 'Cooler A']);
        $old = DB::table('warehouse_batches')->insertGetId(['owner_id' => $vendor->id, 'product_id' => $rose, 'warehouse_location_id' => $cooler, 'qty_remaining' => 12, 'expiration_date' => '2026-10-18', 'batch_number' => 'B-OLD']);
        DB::table('warehouse_batches')->insert(['owner_id' => $vendor->id, 'product_id' => $rose, 'warehouse_location_id' => $cooler, 'qty_remaining' => 30, 'expiration_date' => '2026-10-21']);

        // Wastage: 8 culled this month, 5 last month
        DB::table('warehouse_batch_logs')->insert([
            ['warehouse_batch_id' => $old, 'event_type' => 'CULLED', 'qty_change' => -8, 'created_at' => '2026-10-10 12:00:00'],
            ['warehouse_batch_id' => $old, 'event_type' => 'CULLED', 'qty_change' => -5, 'created_at' => '2026-09-10 12:00:00'],
            ['warehouse_batch_id' => $old, 'event_type' => 'TRANSFERRED', 'qty_change' => 0, 'created_at' => '2026-10-11 12:00:00'],
        ]);

        $d = $this->signIn($vendor)->getJson('/api/procurement/supply-chain/analytics/dashboard')->assertOk()->json('data');

        $this->assertSame(4, $d['orders']['total']);
        $this->assertSame(3, $d['orders']['open']);
        $this->assertEquals(5000, $d['orders']['open_value']);
        $this->assertSame(1, $d['orders']['on_the_way']);
        $this->assertCount(30, $d['orders']['per_day']);

        // On the road first, then the one being prepared; the waiting one isn't incoming yet
        $this->assertSame([$road], [$d['incoming'][0]['id']]);
        $this->assertSame('J&T', $d['incoming'][0]['carrier']);
        $this->assertSame(120, $d['incoming'][0]['quantity']);
        $this->assertCount(2, $d['incoming']);

        $this->assertSame(1, $d['stock']['out_of_stock']);
        $this->assertSame(1, $d['stock']['low_stock']);
        $this->assertSame(['Rose', 'Tulip'], array_column($d['stock']['items'], 'name'));

        $bloomRow = collect($d['suppliers'])->firstWhere('name', 'Bloom Farms');
        $this->assertEquals(50, $bloomRow['fill_rate']);
        $this->assertEquals(3, $bloomRow['avg_lead_days']);
        $this->assertSame(1, $bloomRow['open']);
        $this->assertNotContains('Elsewhere', array_column($d['suppliers'], 'name'));

        $this->assertSame(8, $d['wastage']['units']);
        $this->assertEquals(200, $d['wastage']['cost']);
        $this->assertSame(5, $d['wastage']['previous_units']);
        $this->assertSame('Rose', $d['wastage']['top'][0]['name']);

        $this->assertSame(12, $d['floor']['spoiled_units']);
        $this->assertSame(30, $d['floor']['expiring_units']);
        $this->assertSame('Cooler A', $d['floor']['batches'][0]['where']);
    }

    public function test_staff_need_the_dashboard_permission(): void
    {
        $vendor = $this->shopOwner();
        $staff = Employee::forceCreate([
            'owner_id' => $vendor->id, 'name' => 'Sam', 'email' => fake()->unique()->safeEmail(),
            'department' => 'Procurement', 'password' => 'x', 'status' => 'Active',
        ]);
        $this->signIn($staff)->getJson('/api/procurement/supply-chain/analytics/dashboard')->assertForbidden();

        DB::table('employee_module_permissions')->insert([
            'owner_id' => $vendor->id, 'employee_id' => $staff->id, 'module' => 'sc_dashboard',
            'permission' => 'view', 'access' => 'granular', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->signIn($staff)->getJson('/api/procurement/supply-chain/analytics/dashboard?days=7')
            ->assertOk()
            ->assertJsonPath('data.period_days', 7)
            ->assertJsonPath('data.orders.total', 0);
    }
}
