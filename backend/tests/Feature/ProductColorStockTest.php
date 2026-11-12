<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductController;
use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A product sold in several flower colours keeps a stock count per colour:
 * orders take from (and give back to) the colour bought, the total is always
 * the sum, and total-only changes are spread over the colours.
 */
class ProductColorStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('products');
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('product_name');
            $t->string('sku')->nullable();
            $t->string('category')->nullable();
            $t->string('flower_type')->nullable();
            $t->string('color')->nullable();
            $t->string('color_other')->nullable();
            $t->json('color_options')->nullable();
            $t->decimal('selling_price', 12, 2)->default(0);
            $t->decimal('purchase_price', 12, 2)->nullable();
            $t->integer('quantity_in_stock')->default(0);
            $t->integer('min_stock_level')->default(0);
            $t->string('selling_type')->nullable();
            $t->string('status')->default('active');
            $t->string('moderation_status')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    // The table above is only for these tests: later tests build their own
    protected function tearDown(): void
    {
        Schema::dropIfExists('products');
        // Making a ProductController switches prices to raw for the request; switch back
        Product::$rawPricing = false;
        parent::tearDown();
    }

    private function product(array $colors): Product
    {
        return Product::forceCreate([
            'owner_id' => 1,
            'product_name' => 'Tulip bunch',
            'sku' => 'TUL-' . fake()->unique()->numerify('####'),
            'category' => 'Bouquets',
            'flower_type' => 'focal',
            'color' => 'red',
            'selling_price' => 500,
            'purchase_price' => 200,
            'quantity_in_stock' => 999, // replaced by the colours' sum
            'min_stock_level' => 0,
            'selling_type' => 'bouquet',
            'status' => 'active',
            'color_options' => $colors,
        ]);
    }

    private function counts(Product $p): array
    {
        $p->refresh();
        return [collect($p->color_options)->pluck('quantity', 'name')->all(), (int) $p->quantity_in_stock];
    }

    public function test_the_total_is_the_sum_of_the_colours(): void
    {
        $p = $this->product([['name' => 'Red', 'value' => 'red', 'quantity' => 10], ['name' => 'Pink', 'value' => 'pink', 'quantity' => 5]]);
        $this->assertSame([['Red' => 10, 'Pink' => 5], 15], $this->counts($p));
        $this->assertSame(5, $p->stockFor('pink'));
        $this->assertSame(10, $p->stockFor('Red'));
        $this->assertSame(15, $p->stockFor(null), 'no colour chosen: the whole stock');
        $this->assertSame(15, $p->stockFor('Blue'), 'a colour that is not on the list: the whole stock');
    }

    public function test_an_order_takes_from_its_colour_only_and_never_more_than_it_has(): void
    {
        $p = $this->product([['name' => 'Red', 'value' => 'red', 'quantity' => 10], ['name' => 'Pink', 'value' => 'pink', 'quantity' => 2]]);

        DB::transaction(fn () => $this->assertNotNull(Product::takeStock($p->id, 3, 'Red')));
        $this->assertSame([['Red' => 7, 'Pink' => 2], 9], $this->counts($p));

        // Pink has only 2: asking for 3 changes nothing, even though the total is 9
        DB::transaction(fn () => $this->assertNull(Product::takeStock($p->id, 3, 'Pink')));
        $this->assertSame([['Red' => 7, 'Pink' => 2], 9], $this->counts($p));

        // A cancelled order puts it back on the same colour
        DB::transaction(fn () => Product::putBackStock($p->id, 3, 'red'));
        $this->assertSame([['Red' => 10, 'Pink' => 2], 12], $this->counts($p));
    }

    public function test_a_total_only_change_is_spread_over_the_colours(): void
    {
        $p = $this->product([['name' => 'Red', 'value' => 'red', 'quantity' => 4], ['name' => 'Pink', 'value' => 'pink', 'quantity' => 6]]);

        // Inventory − : comes off the colour with the most first
        $p->update(['quantity_in_stock' => 3]);
        $this->assertSame([['Red' => 3, 'Pink' => 0], 3], $this->counts($p));

        // Received stock without a colour: goes to the first (main) colour
        $p->update(['quantity_in_stock' => 8]);
        $this->assertSame([['Red' => 8, 'Pink' => 0], 8], $this->counts($p));
    }

    public function test_a_product_with_one_shared_stock_works_as_before(): void
    {
        $p = $this->product([]);
        $p->update(['quantity_in_stock' => 5]);
        $this->assertSame(5, $p->fresh()->stockFor('Red'));
        DB::transaction(fn () => $this->assertNotNull(Product::takeStock($p->id, 2, 'Red')));
        $this->assertSame(3, (int) $p->fresh()->quantity_in_stock);
        DB::transaction(fn () => $this->assertNull(Product::takeStock($p->id, 4)));
    }

    public function test_the_product_form_payload_is_checked(): void
    {
        $method = new \ReflectionMethod(ProductController::class, 'colorStockFromRequest');
        $method->setAccessible(true);
        $controller = app(ProductController::class);
        $read = fn (array $input) => $method->invoke($controller, Request::create('/', 'POST', $input));

        $this->assertNull($read([]), 'not sent: left alone');

        $ok = $read(['color_options' => json_encode([
            ['name' => 'x', 'value' => 'red', 'quantity' => 4],
            ['name' => 'Burgundy', 'value' => 'other', 'hex' => 'not-a-colour', 'quantity' => '6'],
        ])]);
        $this->assertSame('red', $ok['attributes']['color']);
        $this->assertSame(10, $ok['attributes']['quantity_in_stock']);
        $this->assertSame(['Red', 'Burgundy'], array_column($ok['attributes']['color_options'], 'name'));
        $this->assertNull($ok['attributes']['color_options'][1]['hex']);

        $this->assertSame(['color_options' => null], $read(['color_options' => json_encode([['value' => 'red', 'quantity' => 4]])])['attributes'], 'one colour clears the list');
        $this->assertArrayHasKey('errors', $read(['color_options' => json_encode([['value' => 'red', 'quantity' => 1], ['value' => 'red', 'quantity' => 2]])]));
        $this->assertArrayHasKey('errors', $read(['color_options' => json_encode([['value' => 'red', 'quantity' => 1], ['value' => 'pink', 'quantity' => -1]])]));
        $this->assertArrayHasKey('errors', $read(['color_options' => json_encode([['value' => 'red', 'quantity' => 1], ['value' => 'other', 'name' => '', 'quantity' => 1]])]));
        $this->assertArrayHasKey('errors', $read(['color_options' => json_encode([['value' => 'gold', 'quantity' => 1], ['value' => 'red', 'quantity' => 1]])]));
    }
}
