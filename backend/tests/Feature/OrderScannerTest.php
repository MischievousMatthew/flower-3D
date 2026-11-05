<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
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

        foreach (['to_processed', 'to_ship', 'to_received'] as $expected) {
            $this->postJson('/api/sc/barcode/scan', ['barcode' => 'BLM-TEST-1', 'scanner_page' => 'auto'])
                ->assertOk()->assertJsonPath('delivery.status', $expected);
        }
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
}
