<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Retrying an unpaid e-wallet order pays for that order's items, so a
 * checkout for different items (e.g. today's cart while last night's attempt
 * was still saved in the tab) starts a new order instead. Unpaid e-wallet
 * attempts aren't orders: customers don't see them as pending.
 */
class CheckoutRetryTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['order_items', 'orders', 'carts', 'users'] as $t) {
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
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->string('order_number')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('payment_status')->default('unpaid');
            $t->string('status')->default('pending');
            $t->string('paymongo_payment_intent_id')->nullable();
            $t->string('paymongo_source_id')->nullable();
            $t->text('paymongo_checkout_url')->nullable();
            $t->json('paymongo_response')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('product_id');
            $t->integer('quantity');
            $t->timestamps();
        });
        Schema::create('carts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('product_id');
            $t->integer('quantity');
            $t->timestamps();
        });
        $this->user = User::forceCreate(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x']);
    }

    private function unpaidOrder(array $items): int
    {
        $id = DB::table('orders')->insertGetId(['user_id' => $this->user->id, 'order_number' => 'ORD-1', 'payment_method' => 'ewallet', 'paymongo_payment_intent_id' => 'cs_1']);
        foreach ($items as [$product, $qty]) {
            DB::table('order_items')->insert(['order_id' => $id, 'product_id' => $product, 'quantity' => $qty]);
        }

        return $id;
    }

    private function cart(int $product, int $qty = 1): int
    {
        return DB::table('carts')->insertGetId(['user_id' => $this->user->id, 'product_id' => $product, 'quantity' => $qty]);
    }

    private function retryAllowed(array $input): bool
    {
        $method = new \ReflectionMethod(CheckoutController::class, 'retryMatchesItems');

        return $method->invoke(app(CheckoutController::class), new Request($input), $this->user);
    }

    public function test_retrying_the_same_cart_items_reuses_the_order(): void
    {
        $order = $this->unpaidOrder([[10, 1], [11, 2]]);
        $ids = [$this->cart(10), $this->cart(11, 2)];

        $this->assertTrue($this->retryAllowed(['retry_order_id' => $order, 'cart_item_ids' => $ids]));
    }

    public function test_a_different_cart_never_pays_for_an_older_order(): void
    {
        // Last night: products 10 and 11. Today: only product 12 selected.
        $order = $this->unpaidOrder([[10, 1], [11, 1]]);
        $today = $this->cart(12);

        $this->assertFalse($this->retryAllowed(['retry_order_id' => $order, 'cart_item_ids' => [$today]]));
    }

    public function test_a_different_quantity_is_a_new_order(): void
    {
        $order = $this->unpaidOrder([[10, 1]]);

        $this->assertFalse($this->retryAllowed(['retry_order_id' => $order, 'product_id' => 10, 'quantity' => 3]));
        $this->assertTrue($this->retryAllowed(['retry_order_id' => $order, 'product_id' => 10, 'quantity' => 1]));
    }

    public function test_someone_elses_order_never_matches(): void
    {
        $order = $this->unpaidOrder([[10, 1]]);
        DB::table('orders')->where('id', $order)->update(['user_id' => 999]);

        $this->assertFalse($this->retryAllowed(['retry_order_id' => $order, 'product_id' => 10, 'quantity' => 1]));
    }

    public function test_the_replaced_attempt_is_closed(): void
    {
        $order = $this->unpaidOrder([[10, 1]]);
        $method = new \ReflectionMethod(CheckoutController::class, 'closeUnfinishedOnlineOrder');
        $method->invoke(app(CheckoutController::class), $order, $this->user->id);

        $closed = Order::find($order);
        $this->assertSame('failed', $closed->status);
        $this->assertNull($closed->paymongo_payment_intent_id);
    }

    public function test_unpaid_e_wallet_attempts_are_not_placed_orders(): void
    {
        $this->unpaidOrder([[10, 1]]);
        DB::table('orders')->insert(['user_id' => $this->user->id, 'payment_method' => 'ewallet', 'payment_status' => 'paid', 'status' => 'processing']);
        DB::table('orders')->insert(['user_id' => $this->user->id, 'payment_method' => 'cod', 'status' => 'pending']);

        $this->assertSame(2, Order::placed()->count());
    }
}
