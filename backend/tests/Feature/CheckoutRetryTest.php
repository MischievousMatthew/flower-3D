<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Http\Controllers\CheckoutController;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\VendorFinanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Checkout safety: every order holds its stock when it's made (so two people
 * can't both buy the last one and stock never goes below zero), a cancelled
 * or failed e-wallet payment ends the order for good and gives the stock
 * back, and a checkout key never makes the same order twice.
 */
class CheckoutRetryTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paymongo.secret_key' => null]);
        foreach (['order_items', 'orders', 'products', 'reservation_availability_cache', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->string('role')->default('customer');
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('product_name');
            $t->integer('quantity_in_stock');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->string('order_number')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('payment_status')->default('unpaid');
            $t->string('status')->default('pending');
            $t->date('reservation_date')->nullable();
            $t->string('paymongo_payment_intent_id')->nullable();
            $t->string('paymongo_source_id')->nullable();
            $t->text('paymongo_checkout_url')->nullable();
            $t->json('paymongo_response')->nullable();
            $t->timestamp('stock_deducted_at')->nullable();
            $t->timestamp('stock_restored_at')->nullable();
            $t->string('checkout_key', 64)->nullable();
            $t->unique(['user_id', 'checkout_key']);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('product_id');
            $t->integer('quantity');
            $t->timestamps();
        });
        $this->user = User::forceCreate(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x']);
    }

    private function product(int $stock): int
    {
        return DB::table('products')->insertGetId(['product_name' => 'Red Roses', 'quantity_in_stock' => $stock]);
    }

    private function order(int $productId, int $qty, array $values = []): Order
    {
        $id = DB::table('orders')->insertGetId(array_merge([
            'user_id' => $this->user->id, 'order_number' => 'ORD-' . uniqid(), 'payment_method' => 'ewallet', 'created_at' => now(),
        ], $values));
        DB::table('order_items')->insert(['order_id' => $id, 'product_id' => $productId, 'quantity' => $qty]);

        return Order::findOrFail($id);
    }

    private function stock(int $productId): int
    {
        return (int) DB::table('products')->where('id', $productId)->value('quantity_in_stock');
    }

    private function invokeCheckout(string $method, ...$args)
    {
        return (new \ReflectionMethod(CheckoutController::class, $method))->invoke(app(CheckoutController::class), ...$args);
    }

    public function test_only_one_of_two_checkouts_gets_the_last_item(): void
    {
        $roses = $this->product(1);
        $first = $this->order($roses, 1);
        $second = $this->order($roses, 1);

        app(VendorFinanceService::class)->deductOrderStock($first);

        try {
            app(VendorFinanceService::class)->deductOrderStock($second);
            $this->fail('The second checkout should have been told it sold out.');
        } catch (OutOfStockException $e) {
            $this->assertStringContainsString('sold out', $e->getMessage());
        }
        $this->assertSame(0, $this->stock($roses));
        $this->assertNull($second->fresh()->stock_deducted_at);
    }

    public function test_asking_for_more_than_is_left_says_how_many_remain(): void
    {
        $roses = $this->product(2);

        $this->expectExceptionMessage('Only 2 Red Roses left');
        app(VendorFinanceService::class)->deductOrderStock($this->order($roses, 5));
    }

    public function test_stock_is_given_back_once(): void
    {
        $roses = $this->product(3);
        $order = $this->order($roses, 2);
        app(VendorFinanceService::class)->deductOrderStock($order);

        app(VendorFinanceService::class)->releaseOrderStock($order);
        app(VendorFinanceService::class)->releaseOrderStock($order);

        $this->assertSame(3, $this->stock($roses));
    }

    public function test_a_cancelled_payment_ends_the_order_and_puts_the_stock_back(): void
    {
        $roses = $this->product(1);
        $order = $this->order($roses, 1, ['paymongo_payment_intent_id' => 'cs_test']);
        app(VendorFinanceService::class)->deductOrderStock($order);

        $this->assertSame('closed', $this->invokeCheckout('closeUnfinishedOnlineOrder', $order));

        $order->refresh();
        $this->assertSame('failed', $order->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertNull($order->paymongo_checkout_url);
        $this->assertSame('cancelled', $order->paymentFailure()['reason']);
        $this->assertSame(1, $this->stock($roses));
    }

    public function test_the_same_key_after_a_failed_payment_says_it_failed(): void
    {
        $order = $this->order($this->product(1), 1, ['status' => 'failed', 'payment_status' => 'failed', 'checkout_key' => 'key-12345678']);

        $response = $this->invokeCheckout('existingOrderResponse', $order);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('payment_failed', $response->getData(true)['code']);
    }

    public function test_the_same_key_while_paying_returns_the_same_payment_page(): void
    {
        $order = $this->order($this->product(1), 1, ['paymongo_checkout_url' => 'https://checkout.paymongo.com/cs_1', 'checkout_key' => 'key-12345678']);

        $data = $this->invokeCheckout('existingOrderResponse', $order)->getData(true);

        $this->assertSame($order->id, $data['order_id']);
        $this->assertSame('https://checkout.paymongo.com/cs_1', $data['checkout_url']);
    }

    public function test_one_key_can_only_make_one_order(): void
    {
        $this->order($this->product(1), 1, ['checkout_key' => 'key-12345678']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->order($this->product(1), 1, ['checkout_key' => 'key-12345678']);
    }

    public function test_an_unpaid_hold_runs_out(): void
    {
        $roses = $this->product(1);
        $this->order($roses, 1);
        $this->assertSame(1, Order::takingSlot()->count());

        $this->travel(Order::PAYMENT_HOLD_MINUTES + 1)->minutes();
        $this->assertSame(0, Order::takingSlot()->count());
        $this->assertSame(0, Order::placed()->count());
    }
}
