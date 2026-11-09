<?php

namespace Tests\Feature;

use App\Services\ReceiptFileStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/** The one order scanner: each scan moves a delivery one step forward, whatever step it is at. */
class OrderScannerTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createFinanceTables();
        Schema::table('deliveries', function (Blueprint $t) {
            $t->string('delivery_id')->nullable();
            $t->string('barcode')->nullable();
            $t->unsignedBigInteger('last_scanned_by')->nullable();
        });
        (require base_path('database/migrations/2026_11_10_120000_add_photo_to_deliveries.php'))->up();
        Schema::dropIfExists('delivery_logs');
        Schema::create('delivery_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('delivery_id');
            $t->string('status');
            $t->unsignedBigInteger('scanned_by')->nullable();
            $t->timestamp('scanned_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    private function delivery($vendor, string $status = 'pending', string $code = 'BLM-TEST-1'): int
    {
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-1', 'vendor_id' => $vendor->id, 'status' => 'processing', 'payment_status' => 'paid',
            'payment_method' => 'cod', 'total_amount' => 500, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('deliveries')->insertGetId([
            'owner_id' => $vendor->id, 'order_id' => $orderId, 'status' => $status, 'delivery_id' => $code, 'barcode' => $code,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_each_scan_moves_the_order_to_its_next_step(): void
    {
        $vendor = $this->shopOwner();
        $id = $this->delivery($vendor);
        $this->signIn($vendor);

        foreach (['to_processed', 'to_ship'] as $expected) {
            $this->postJson('/api/sc/barcode/scan', ['barcode' => 'BLM-TEST-1', 'scanner_page' => 'auto'])
                ->assertOk()->assertJsonPath('delivery.status', $expected);
        }

        // It doesn't go out for delivery until there's a photo of the order
        $this->postJson('/api/sc/barcode/scan', ['barcode' => 'BLM-TEST-1', 'scanner_page' => 'auto'])
            ->assertStatus(422)->assertJsonPath('message', 'Take or attach a photo of the order before it goes out for delivery. The customer sees it on their order.');
        DB::table('deliveries')->where('id', $id)->update(['photo_url' => 'https://img.test/order.jpg']);
        $this->postJson('/api/sc/barcode/scan', ['barcode' => 'BLM-TEST-1', 'scanner_page' => 'auto'])
            ->assertOk()->assertJsonPath('delivery.status', 'to_received');
        $this->assertSame(3, DB::table('delivery_logs')->where('delivery_id', $id)->count());

        // Dropped off: nothing more to scan, and it says why
        $this->postJson('/api/sc/barcode/scan', ['barcode' => 'BLM-TEST-1', 'scanner_page' => 'auto'])
            ->assertStatus(422)->assertJsonPath('message', 'This order was already dropped off. It is waiting for the customer to confirm they got it.');
        $this->assertSame('to_received', DB::table('deliveries')->where('id', $id)->value('status'));
    }

    public function test_another_shops_order_cant_be_scanned(): void
    {
        $other = $this->shopOwner();
        $this->delivery($other, 'pending', 'BLM-OTHER');
        $this->signIn($this->shopOwner());

        $this->postJson('/api/sc/barcode/scan', ['barcode' => 'BLM-OTHER', 'scanner_page' => 'auto'])->assertStatus(422);
        $this->assertSame('pending', DB::table('deliveries')->where('barcode', 'BLM-OTHER')->value('status'));
    }

    public function test_the_queue_can_list_several_steps_at_once(): void
    {
        $vendor = $this->shopOwner();
        $this->delivery($vendor, 'pending', 'BLM-A');
        $this->delivery($vendor, 'to_ship', 'BLM-B');
        $this->delivery($vendor, 'completed', 'BLM-C');

        $res = $this->signIn($vendor)->getJson('/api/sc/orders?status=pending,to_processed,to_ship&per_page=100')->assertOk();
        $this->assertEqualsCanonicalizing(['BLM-A', 'BLM-B'], array_column($res->json('data'), 'barcode'));
    }

    public function test_a_photo_of_the_order_is_attached_before_it_goes_out(): void
    {
        $vendor = $this->shopOwner();
        $id = $this->delivery($vendor, 'to_ship');
        $orderId = (int) DB::table('deliveries')->where('id', $id)->value('order_id');
        $this->app->instance(ReceiptFileStore::class, new class extends ReceiptFileStore {
            public function store(UploadedFile $file, string $folder): array
            {
                return ['url' => "https://img.test/{$folder}/order.jpg", 'public_id' => 'p1'];
            }

            public function remove(?string $publicId): void {}
        });
        $this->signIn($vendor);

        $this->post("/api/vendor/orders/{$orderId}/delivery-photo", ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->post("/api/vendor/orders/{$orderId}/delivery-photo", ['photo' => UploadedFile::fake()->image('bouquet.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.photo_url', 'https://img.test/delivery-photos/order.jpg');

        $row = DB::table('deliveries')->find($id);
        $this->assertSame('https://img.test/delivery-photos/order.jpg', $row->photo_url);
        $this->assertNotNull($row->photo_taken_at);
    }

    public function test_only_the_customer_completes_an_order(): void
    {
        $vendor = $this->shopOwner();
        $id = $this->delivery($vendor, 'to_received');
        $orderId = (int) DB::table('deliveries')->where('id', $id)->value('order_id');
        $this->signIn($vendor);

        // Not from the shop's order page...
        $this->putJson("/api/vendor/orders/{$orderId}/status", ['status' => 'completed'])
            ->assertStatus(422)->assertJsonPath('message', 'Only the customer can complete this order, by marking it received on their side.');
        // ...nor from Deliveries in the ERP
        $this->expectExceptionMessage('Only the customer can complete this order');
        app(\App\Services\DeliveryService::class)->advanceStatus(\App\Models\Delivery::findOrFail($id), 'completed');
    }
}
