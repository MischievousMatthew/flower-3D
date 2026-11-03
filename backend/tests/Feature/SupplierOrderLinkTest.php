<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderSupplierLink;
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesPurchasingTables;
use Tests\TestCase;

/**
 * Suppliers answer a purchase order from the private link in its email:
 * confirm (with how many they can supply), decline, or mark it shipped.
 * Links expire and a newer email replaces them. No order goes out at ₱0.
 */
class SupplierOrderLinkTest extends TestCase
{
    use CreatesPurchasingTables;

    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['user_notifications', 'purchase_order_supplier_links', 'products', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->string('role')->default('vendor');
            $t->timestamps();
        });
        Schema::create('products', fn (Blueprint $t) => $t->id());
        Schema::dropIfExists('vendor_applications');
        Schema::create('vendor_applications', function (Blueprint $t) {
            $t->id();
            $t->string('email')->nullable();
            $t->string('store_name')->nullable();
            $t->string('status')->nullable();
        });
        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('broadcast_id')->nullable();
            $t->string('type');
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('link_url')->nullable();
            $t->boolean('important')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        $this->createPurchasingTables();

        DB::table('users')->insert(['id' => 3, 'name' => 'Owner', 'email' => 'shop@example.com']);
        DB::table('vendor_applications')->insert(['email' => 'shop@example.com', 'store_name' => 'Flux Flower Store', 'status' => 'approved']);
        $supplierId = DB::table('suppliers')->insertGetId(['owner_id' => 3, 'company_name' => 'Blooms', 'contact_person' => 'Marie', 'email' => 'blooms@example.com']);
        $this->orderId = DB::table('purchase_orders')->insertGetId(['owner_id' => 3, 'supplier_id' => $supplierId, 'order_number' => 'PO-1', 'status' => 'pending', 'total_amount' => 13200]);
        DB::table('purchase_order_items')->insert(['owner_id' => 3, 'purchase_order_id' => $this->orderId, 'product_name' => 'Tulip', 'quantity' => 1100, 'price' => 12]);
    }

    private function token(): string
    {
        return PurchaseOrderSupplierLink::issue(PurchaseOrder::findOrFail($this->orderId))[1];
    }

    private function itemId(): int
    {
        return (int) DB::table('purchase_order_items')->value('id');
    }

    public function test_the_supplier_sees_the_order_with_its_prices(): void
    {
        $this->getJson('/api/supplier-orders/' . $this->token())
            ->assertOk()
            ->assertJsonPath('data.order_number', 'PO-1')
            ->assertJsonPath('data.shop_name', 'Flux Flower Store')
            ->assertJsonPath('data.stage', 'awaiting')
            ->assertJsonPath('data.items.0.price', 12)
            ->assertJsonPath('data.total', 13200);
    }

    public function test_confirming_part_of_an_order_updates_it_for_the_shop(): void
    {
        $token = $this->token();

        $this->postJson("/api/supplier-orders/{$token}/confirm", [
            'items' => [['id' => $this->itemId(), 'qty' => 900]],
            'delivery_date' => now()->addDays(5)->toDateString(),
            'note' => 'Short on tulips this week',
        ])->assertOk()->assertJsonPath('data.stage', 'confirmed');

        $order = PurchaseOrder::findOrFail($this->orderId);
        $this->assertSame('processing', $order->status);
        $this->assertEquals(10800, (float) $order->total_amount);
        $this->assertSame(200, (int) DB::table('purchase_order_items')->value('cancelled_qty'));
        $this->assertDatabaseHas('purchase_order_events', ['event' => 'supplier_confirmed', 'actor_name' => 'Blooms']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => 3]);
    }

    public function test_a_supplier_cannot_confirm_more_than_was_ordered(): void
    {
        $this->postJson('/api/supplier-orders/' . $this->token() . '/confirm', ['items' => [['id' => $this->itemId(), 'qty' => 2000]]])
            ->assertStatus(422);

        $this->assertSame('pending', PurchaseOrder::findOrFail($this->orderId)->status);
        $this->assertSame(0, (int) DB::table('purchase_order_items')->value('cancelled_qty'));
    }

    public function test_declining_sends_it_back_to_draft_with_the_reason(): void
    {
        $token = $this->token();

        $this->postJson("/api/supplier-orders/{$token}/decline", ['reason' => 'Out of tulips until December'])
            ->assertOk()->assertJsonPath('data.stage', 'declined');

        $order = PurchaseOrder::findOrFail($this->orderId);
        $this->assertSame('draft', $order->status);
        $this->assertSame('Out of tulips until December', $order->supplier_note);
        // The link is done once declined
        $this->getJson("/api/supplier-orders/{$token}")->assertStatus(410);
    }

    public function test_shipping_comes_after_confirming(): void
    {
        $token = $this->token();
        $this->postJson("/api/supplier-orders/{$token}/shipped")->assertStatus(409);

        $this->postJson("/api/supplier-orders/{$token}/confirm", ['items' => [['id' => $this->itemId(), 'qty' => 1100]]])->assertOk();
        $this->postJson("/api/supplier-orders/{$token}/shipped", ['note' => 'Lalamove'])->assertOk()->assertJsonPath('data.stage', 'shipped');

        $this->assertSame('shipped', PurchaseOrder::findOrFail($this->orderId)->status);
    }

    public function test_answering_twice_changes_nothing(): void
    {
        $token = $this->token();
        $this->postJson("/api/supplier-orders/{$token}/confirm", ['items' => [['id' => $this->itemId(), 'qty' => 1100]]])->assertOk();

        $this->postJson("/api/supplier-orders/{$token}/decline", ['reason' => 'Changed my mind'])
            ->assertStatus(409)->assertJsonPath('code', 'already_answered');
        $this->assertSame('processing', PurchaseOrder::findOrFail($this->orderId)->status);
    }

    public function test_the_link_expires(): void
    {
        $token = $this->token();
        $this->travel(PurchaseOrderSupplierLink::VALID_DAYS + 1)->days();

        $this->getJson("/api/supplier-orders/{$token}")->assertStatus(410)->assertJsonPath('code', 'link_expired');
    }

    public function test_a_newer_email_replaces_the_old_link(): void
    {
        $old = $this->token();
        $new = $this->token();

        $this->getJson("/api/supplier-orders/{$old}")->assertStatus(410)->assertJsonPath('code', 'link_replaced');
        $this->getJson("/api/supplier-orders/{$new}")->assertOk();
    }

    public function test_a_made_up_link_is_refused(): void
    {
        $this->getJson('/api/supplier-orders/' . str_repeat('x', 48))->assertNotFound();
    }

    public function test_cancelling_a_sent_order_tells_the_supplier_and_ends_their_link(): void
    {
        \Illuminate\Support\Facades\Http::fake(['api.brevo.com/*' => \Illuminate\Support\Facades\Http::response(['messageId' => 'x'], 201)]);
        DB::table('purchase_orders')->where('id', $this->orderId)->update(['sent_at' => now()]);
        $token = $this->token();

        $result = app(OrderService::class)->cancel($this->orderId, 3, 'Ordered at the wrong price');

        $this->assertTrue($result['supplier_told']);
        $order = PurchaseOrder::findOrFail($this->orderId);
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Ordered at the wrong price', $order->cancel_reason);
        // Nothing is expected any more
        $this->assertSame(1100, (int) DB::table('purchase_order_items')->value('cancelled_qty'));
        $this->assertDatabaseHas('purchase_order_events', ['event' => 'cancelled', 'from_status' => 'pending', 'to_status' => 'cancelled']);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r['subject'], 'Cancelled') && str_contains((string) $r['htmlContent'], 'Ordered at the wrong price'));
        $this->getJson("/api/supplier-orders/{$token}")->assertStatus(410);
    }

    public function test_an_order_not_yet_sent_is_cancelled_without_emailing(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        DB::table('purchase_orders')->where('id', $this->orderId)->update(['status' => 'draft']);

        $this->assertNull(app(OrderService::class)->cancel($this->orderId, 3, 'Not needed')['supplier_told']);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_an_order_that_has_started_arriving_cannot_be_cancelled(): void
    {
        DB::table('purchase_order_items')->update(['received_qty' => 100]);

        $this->expectExceptionMessage('already arrived');
        app(OrderService::class)->cancel($this->orderId, 3, 'Too late');
    }

    public function test_a_cancelled_order_cannot_be_cancelled_again(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        app(OrderService::class)->cancel($this->orderId, 3, 'Not needed');

        $this->expectExceptionMessage('already cancelled');
        app(OrderService::class)->cancel($this->orderId, 3, 'Again');
    }

    public function test_an_order_at_zero_pesos_is_never_approved(): void
    {
        DB::table('purchase_orders')->where('id', $this->orderId)->update(['status' => 'pending_approval']);
        DB::table('purchase_order_items')->update(['price' => 0]);

        $this->expectExceptionMessage('Add the agreed price for Tulip');
        app(OrderService::class)->approve($this->orderId, 3, false);
    }
}
