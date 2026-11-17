<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The admin console filters and counts orders in SQL (whereDisplayStatus);
 * everyone else reads Order::displayStatus(). Both must always agree.
 */
class OrderDisplayStatusQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['deliveries', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number');
            $t->string('status')->nullable();
            $t->timestamps();
        });
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('status')->nullable();
            $t->timestamps();
        });
    }

    public function test_the_sql_filter_matches_display_status_for_every_combination(): void
    {
        $orderStatuses = ['pending', 'processing', 'confirmed', 'shipped', 'delivered', 'out_for_delivery', 'completed', 'cancelled', 'failed', 'returned', 'refunded', 'something_new'];
        // null = no delivery row; '' = a delivery with no step yet
        $deliveryStatuses = [null, '', 'pending', 'to_processed', 'to_ship', 'to_received', 'to_receive', 'completed', 'returned', 'refunded', 'odd_step'];

        $n = 0;
        foreach ($orderStatuses as $status) {
            foreach ($deliveryStatuses as $step) {
                $id = DB::table('orders')->insertGetId(['order_number' => 'ORD-' . ++$n, 'status' => $status]);
                if ($step !== null) {
                    DB::table('deliveries')->insert(['order_id' => $id, 'status' => $step]);
                }
            }
        }

        $expected = Order::query()->with('delivery')->get()->groupBy(fn (Order $o) => $o->displayStatus())->map->pluck('id');
        $this->assertCount($n, $expected->flatten());

        foreach (array_keys(Order::STATUS_LABELS) as $key) {
            $this->assertEqualsCanonicalizing(
                ($expected[$key] ?? collect())->all(),
                Order::query()->whereDisplayStatus([$key])->pluck('id')->all(),
                "Orders shown as '{$key}' don't match the SQL filter"
            );
        }

        // Several at once, like the admin's "Returned / refunded" tab
        $this->assertEqualsCanonicalizing(
            collect([$expected['returned'] ?? [], $expected['refunded'] ?? []])->flatten()->all(),
            Order::query()->whereDisplayStatus(['returned', 'refunded'])->pluck('id')->all()
        );
        $this->assertSame(0, Order::query()->whereDisplayStatus(['not_a_status'])->count());
    }
}
