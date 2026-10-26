<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ReceiptFileStore;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\CreatesPurchasingTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Receiving & quality inspection: delivered, accepted, rejected and missing
 * stems per line, receipts and photos, and only accepted stems in stock.
 */
class ReceivingInspectionTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, CreatesPurchasingTables, SignsInWithApiToken;

    private const BASE = '/api/procurement/supply-chain/receiving';

    /** Files "stored" by the fake store */
    private array $stored = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        $this->createPurchasingTables();
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $test = $this;
        $this->app->instance(ReceiptFileStore::class, new class($test) extends ReceiptFileStore {
            public function __construct(private $test) {}
            public function store(UploadedFile $file, string $folder): array
            {
                $this->test->remember($folder . '/' . $file->getClientOriginalName());
                return ['url' => "https://files.test/{$folder}/{$file->getClientOriginalName()}", 'public_id' => "{$folder}/{$file->getClientOriginalName()}"];
            }
            public function remove(?string $publicId): void
            {
                $this->test->forget($publicId);
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** How many files were ever stored */
    private int $uploads = 0;

    public function remember(string $id): void
    {
        $this->stored[] = $id;
        $this->uploads++;
    }

    public function forget(?string $id): void
    {
        $this->stored = array_values(array_diff($this->stored, [$id]));
    }

    /** An approved order: 100 roses and 10 eucalyptus */
    private function order(User $vendor): PurchaseOrder
    {
        $supplier = DB::table('suppliers')->insertGetId(['owner_id' => $vendor->id, 'company_name' => 'Bloom Farms', 'email' => 'orders@bloom.ph']);
        foreach ([['Red rose', 'RS'], ['Eucalyptus', 'EU']] as [$name, $sku]) {
            DB::table('products')->insert(['owner_id' => $vendor->id, 'product_name' => $name, 'sku' => $sku, 'quantity_in_stock' => 0]);
        }
        $id = $this->signIn($vendor)->postJson('/api/procurement/supply-chain/orders', [
            'supplier_id' => $supplier, 'action' => 'approve',
            'items' => [['product_name' => 'Red rose', 'quantity' => 100, 'price' => 18.5], ['product_name' => 'Eucalyptus', 'quantity' => 10, 'price' => 150]],
        ])->assertCreated()->json('id');

        return PurchaseOrder::with('items')->findOrFail($id);
    }

    private function stock(string $name): int
    {
        return (int) DB::table('products')->where('product_name', $name)->value('quantity_in_stock');
    }

    public function test_only_accepted_stems_go_into_stock_and_the_rest_stays_owed(): void
    {
        $vendor = $this->shopOwner();
        $order = $this->order($vendor);
        [$roses, $euc] = [$order->items->firstWhere('product_name', 'Red rose'), $order->items->firstWhere('product_name', 'Eucalyptus')];
        $cooler = DB::table('warehouse_locations')->insertGetId(['owner_id' => $vendor->id, 'name' => 'Cooler A']);

        // 90 roses came: 80 passed, 10 wilted; eucalyptus didn't come
        $res = $this->post(self::BASE, [
            'purchase_order_id' => $order->id,
            'received_date'     => '2026-10-20',
            'delivery_ref'      => 'DR-5521',
            'lines'             => json_encode([
                ['item_id' => $roses->id, 'delivered_qty' => 90, 'accepted_qty' => 80, 'condition' => 'slightly_open', 'checks' => ['stems_firm', 'no_pests'], 'reject_reason' => 'wilted', 'warehouse_location_id' => $cooler, 'expiration_date' => '2026-10-27'],
                ['item_id' => $euc->id, 'delivered_qty' => 0, 'accepted_qty' => 0],
            ]),
            'receipt' => UploadedFile::fake()->create('dr-5521.pdf', 120, 'application/pdf'),
            'photos'  => [UploadedFile::fake()->image('wilted.jpg'), UploadedFile::fake()->image('boxes.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $res->assertJsonPath('data.accepted_total', 80)
            ->assertJsonPath('data.rejected_total', 10)
            ->assertJsonPath('data.missing_total', 10)
            ->assertJsonPath('data.order.status', 'partially_received');
        $this->assertCount(3, $res->json('data.files'));
        $this->assertSame(80, $this->stock('Red rose'));

        $batch = DB::table('warehouse_batches')->first();
        $this->assertSame(80, (int) $batch->qty_received);
        $this->assertSame($cooler, (int) $batch->warehouse_location_id);
        $this->assertSame('aging', $batch->condition_status);
        $this->assertStringStartsWith('2026-10-27', $batch->expiration_date);

        $roses->refresh();
        $this->assertSame([80, 10, 20], [$roses->received_qty, $roses->rejected_qty, $roses->outstanding()]);

        // The replacement 20 arrive and the eucalyptus is closed: it isn't coming
        $this->post(self::BASE, [
            'purchase_order_id' => $order->id,
            'received_date'     => '2026-10-20',
            'lines'             => json_encode([
                ['item_id' => $roses->id, 'delivered_qty' => 20, 'accepted_qty' => 20, 'condition' => 'fresh'],
                ['item_id' => $euc->id, 'delivered_qty' => 0, 'accepted_qty' => 0, 'close_short' => true],
            ]),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.order.status', 'completed');

        $this->assertSame(100, $this->stock('Red rose'));
        $this->assertSame(0, $this->stock('Eucalyptus'));
        $this->assertSame(10, $euc->fresh()->cancelled_qty);

        $note = DB::table('purchase_order_events')->where('event', 'inspected')->orderBy('id')->value('note');
        $this->assertStringContainsString('Red rose: 80 accepted, 10 rejected (wilted), 10 missing', $note);
    }

    public function test_inspection_rules_are_explained_per_line(): void
    {
        $vendor = $this->shopOwner();
        $order = $this->order($vendor);
        $roses = $order->items->firstWhere('product_name', 'Red rose');
        $post = fn (array $line) => $this->postJson(self::BASE, ['purchase_order_id' => $order->id, 'received_date' => '2026-10-20', 'lines' => [$line + ['item_id' => $roses->id]]]);

        $post(['delivered_qty' => 10, 'accepted_qty' => 12, 'condition' => 'fresh'])->assertStatus(422)->assertJsonValidationErrors('lines.0.accepted_qty');
        $post(['delivered_qty' => 10, 'accepted_qty' => 10])->assertStatus(422)->assertJsonValidationErrors('lines.0.condition');
        $post(['delivered_qty' => 10, 'accepted_qty' => 5, 'condition' => 'damaged'])->assertStatus(422)->assertJsonValidationErrors('lines.0.reject_reason');
        $post(['delivered_qty' => 150, 'accepted_qty' => 101, 'condition' => 'fresh', 'reject_reason' => 'other'])->assertStatus(422)
            ->assertJsonPath('errors', ['lines.0.accepted_qty' => ['Red rose: only 100 are still owed on this order.']]);
        $post(['delivered_qty' => 0, 'accepted_qty' => 0])->assertStatus(422)->assertJsonPath('message', 'Enter what was delivered for at least one item.');

        // Nothing went into stock and no files were left behind
        $this->assertSame(0, $this->stock('Red rose'));
        $this->assertSame(0, DB::table('purchase_order_receipts')->count());
    }

    public function test_a_failed_save_removes_the_files_it_stored(): void
    {
        $vendor = $this->shopOwner();
        $order = $this->order($vendor);
        $euc = $order->items->firstWhere('product_name', 'Eucalyptus');
        // The stock item was removed after ordering: putting it into stock fails after the upload
        DB::table('products')->where('product_name', 'Eucalyptus')->delete();

        $this->post(self::BASE, [
            'purchase_order_id' => $order->id,
            'received_date'     => '2026-10-20',
            'lines'             => json_encode([['item_id' => $euc->id, 'delivered_qty' => 10, 'accepted_qty' => 10, 'condition' => 'fresh']]),
            'photos'            => [UploadedFile::fake()->image('box.jpg')],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(1, $this->uploads);
        $this->assertSame([], $this->stored);
        $this->assertSame(0, DB::table('purchase_order_receipts')->count());
    }

    public function test_warehouse_or_purchasing_staff_can_receive_others_cannot(): void
    {
        $vendor = $this->shopOwner();
        $order = $this->order($vendor);
        $roses = $order->items->firstWhere('product_name', 'Red rose');
        $staff = function (string $module, string $permission) use ($vendor) {
            $e = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Staff', 'email' => fake()->unique()->safeEmail(), 'department' => 'Procurement', 'password' => 'x', 'status' => 'Active']);
            DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $e->id, 'module' => $module, 'permission' => $permission, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);

            return $e;
        };
        $body = ['purchase_order_id' => $order->id, 'received_date' => '2026-10-20', 'lines' => [['item_id' => $roses->id, 'delivered_qty' => 5, 'accepted_qty' => 5, 'condition' => 'fresh']]];

        $this->signIn($staff('warehouse', 'view'))->postJson(self::BASE, $body)->assertForbidden();
        $this->getJson(self::BASE . '/open-orders')->assertOk()->assertJsonPath('data.0.items.0.outstanding', 100);
        $this->signIn($staff('warehouse', 'create'))->postJson(self::BASE, $body)->assertCreated();
        $this->signIn($staff('sc_orders', 'edit'))->postJson(self::BASE, $body)->assertCreated();

        // Recent deliveries, and the ones with problems
        $this->signIn($vendor)->getJson(self::BASE)->assertOk()->assertJsonPath('total', 2);
        $this->getJson(self::BASE . '?issues=1')->assertOk()->assertJsonPath('total', 2);
    }
}
