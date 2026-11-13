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
            $t->json('composition')->nullable();
            $t->string('stock_kind', 20)->nullable();
            $t->string('stock_unit', 20)->nullable();
            $t->string('pack_unit', 20)->nullable();
            $t->unsignedInteger('pack_size')->nullable();
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

    private function flower(string $name, int $stock, array $colors = []): Product
    {
        return Product::forceCreate([
            'owner_id' => 1, 'product_name' => $name, 'sku' => 'F-' . fake()->unique()->numerify('####'),
            'color' => 'red', 'selling_price' => 50, 'purchase_price' => 15, 'quantity_in_stock' => $stock,
            'min_stock_level' => 0, 'selling_type' => 'per_piece', 'status' => 'active', 'color_options' => $colors ?: null,
        ]);
    }

    private function bouquet(array $composition, int $ownStock = 50): Product
    {
        return Product::forceCreate([
            'owner_id' => 1, 'product_name' => 'Rose bouquet', 'sku' => 'B-' . fake()->unique()->numerify('####'),
            'color' => 'red', 'selling_price' => 900, 'purchase_price' => 300, 'quantity_in_stock' => $ownStock,
            'min_stock_level' => 0, 'selling_type' => 'bouquet', 'status' => 'active', 'composition' => $composition,
        ]);
    }

    public function test_a_linked_bouquets_stock_comes_from_its_flowers(): void
    {
        $rose = $this->flower('Red rose', 100);
        $fern = $this->flower('Leatherleaf', 9);
        $b = $this->bouquet([
            ['flower' => 'Rose', 'count' => 12, 'product_id' => $rose->id],
            ['flower' => 'Leatherleaf', 'count' => 2, 'product_id' => $fern->id],
            ['flower' => 'Ribbon', 'count' => 1], // not linked: doesn't limit it
        ]);

        // 100 ÷ 12 = 8, 9 ÷ 2 = 4: the lowest wins, its own 50 is ignored
        $this->assertSame(4, (int) $b->fresh()->quantity_in_stock);

        // The flowers' stock changes: the bouquet follows
        $fern->update(['quantity_in_stock' => 40]);
        $this->assertSame(8, (int) $b->fresh()->quantity_in_stock);
        $rose->update(['quantity_in_stock' => 11]);
        $this->assertSame(0, (int) $b->fresh()->quantity_in_stock);
    }

    public function test_selling_a_linked_bouquet_takes_its_flowers(): void
    {
        $rose = $this->flower('Red rose', 100);
        $plain = $this->flower('Tulip', 10);
        $b = $this->bouquet([['flower' => 'Rose', 'count' => 12, 'product_id' => $rose->id]]);

        // 2 bouquets and 3 tulips: 24 roses and 3 tulips, the bouquet itself isn't taken
        $plan = Product::stockPlan([[$b->id, null, 2], [$plain->id, null, 3]]);
        $this->assertSame([[$rose->id, null, 24, $b->id], [$plain->id, null, 3, null]], $plan);

        DB::transaction(function () use ($plan) {
            foreach ($plan as [$id, $color, $qty]) {
                $this->assertNotNull(Product::takeStock($id, $qty, $color));
            }
        });
        $this->assertSame(76, (int) $rose->fresh()->quantity_in_stock);
        $this->assertSame(6, (int) $b->fresh()->quantity_in_stock, '76 ÷ 12');

        // Not enough roses for 7 more: nothing is taken
        DB::transaction(fn () => $this->assertNull(Product::takeStock($rose->id, 7 * 12)));
        $this->assertSame(76, (int) $rose->fresh()->quantity_in_stock);

        // Cancelled: the roses go back and the bouquet follows
        DB::transaction(fn () => Product::putBackStock($rose->id, 24));
        $this->assertSame(100, (int) $rose->fresh()->quantity_in_stock);
        $this->assertSame(8, (int) $b->fresh()->quantity_in_stock);
    }

    public function test_a_linked_flower_counted_per_colour_uses_that_colours_stock(): void
    {
        $rose = $this->flower('Rose', 0, [['name' => 'Red', 'value' => 'red', 'quantity' => 30], ['name' => 'Pink', 'value' => 'pink', 'quantity' => 6]]);
        $b = $this->bouquet([['flower' => 'Rose', 'color' => 'Pink', 'count' => 3, 'product_id' => $rose->id]]);
        $this->assertSame(2, (int) $b->fresh()->quantity_in_stock, '6 pink ÷ 3');

        $plan = Product::stockPlan([[$b->id, null, 2]]);
        DB::transaction(fn () => Product::takeStock($plan[0][0], $plan[0][2], $plan[0][1]));
        $this->assertSame([['Red' => 30, 'Pink' => 0], 30], $this->counts($rose));
        $this->assertSame(0, (int) $b->fresh()->quantity_in_stock);
    }

    public function test_a_bouquets_wrapper_sheets_come_from_the_shared_wrapper_stock(): void
    {
        $rose = $this->flower('Red rose', 100);
        $kraft = Product::forceCreate([
            'owner_id' => 1, 'product_name' => 'Kraft Paper', 'sku' => 'W-1', 'quantity_in_stock' => 9, 'min_stock_level' => 0,
            'stock_kind' => 'wrapping', 'stock_unit' => 'sheet', 'moderation_status' => Product::MODERATION_INTERNAL, 'status' => 'active',
        ]);
        $b = $this->bouquet([
            ['flower' => 'Rose', 'count' => 12, 'product_id' => $rose->id],
            ['flower' => 'Kraft Paper', 'count' => 2, 'product_id' => $kraft->id, 'kind' => 'wrapper'],
        ]);

        // 100 roses ÷ 12 = 8, 9 sheets ÷ 2 = 4: the wrapper limits it
        $this->assertSame(4, (int) $b->fresh()->quantity_in_stock);

        // Selling 2 takes 24 roses and 4 sheets
        $plan = Product::stockPlan([[$b->id, null, 2]]);
        $this->assertSame([[$rose->id, null, 24, $b->id], [$kraft->id, null, 4, $b->id]], $plan);
        DB::transaction(function () use ($plan) {
            foreach ($plan as [$id, $color, $qty]) {
                $this->assertNotNull(Product::takeStock($id, $qty, $color));
            }
        });
        $this->assertSame([76, 5, 2], [(int) $rose->fresh()->quantity_in_stock, (int) $kraft->fresh()->quantity_in_stock, (int) $b->fresh()->quantity_in_stock]);

        // The form: a wrapper row must be one of the shop's wrappers
        $method = new \ReflectionMethod(ProductController::class, 'compositionFromRequest');
        $method->setAccessible(true);
        $read = fn ($rows) => $method->invoke(app(ProductController::class), Request::create('/', 'POST', ['composition' => json_encode($rows)]), 'bouquet', 1, 999);
        $ok = $read([['flower' => 'Kraft Paper', 'count' => 2, 'product_id' => $kraft->id, 'kind' => 'wrapper', 'color' => 'ignored']]);
        $this->assertSame(['flower' => 'Kraft Paper', 'color' => null, 'count' => 2, 'cost' => null, 'product_id' => $kraft->id, 'kind' => 'wrapper'], $ok['attributes']['composition'][0]);
        $this->assertArrayHasKey('errors', $read([['flower' => 'Kraft', 'count' => 2, 'kind' => 'wrapper']]), 'no wrapper picked');
        $this->assertArrayHasKey('errors', $read([['flower' => 'Rose', 'count' => 2, 'product_id' => $rose->id, 'kind' => 'wrapper']]), 'a flower is not a wrapper');

        // Customers don't see the wrapper in "What's inside"
        $customer = new \ReflectionMethod(\App\Http\Controllers\Customer\CustomerProductController::class, 'composition');
        $customer->setAccessible(true);
        $inside = $customer->invoke(app(\App\Http\Controllers\Customer\CustomerProductController::class), $b->fresh()->composition);
        $this->assertSame(['Rose'], array_column($inside, 'flower'));
    }

    public function test_a_bouquets_filler_comes_from_the_shared_filler_stock(): void
    {
        $rose = $this->flower('Red rose', 100);
        $breath = Product::forceCreate([
            'owner_id' => 1, 'product_name' => "Baby's breath", 'sku' => 'F-1', 'quantity_in_stock' => 6, 'min_stock_level' => 0,
            'stock_kind' => 'greenery', 'stock_unit' => 'portion', 'moderation_status' => Product::MODERATION_INTERNAL, 'status' => 'active',
        ]);
        $b = $this->bouquet([
            ['flower' => 'Rose', 'count' => 12, 'product_id' => $rose->id],
            ['flower' => "Baby's breath", 'count' => 2, 'product_id' => $breath->id, 'kind' => 'filler'],
        ]);

        // 100 roses ÷ 12 = 8, 6 bouquets' worth ÷ 2 = 3: the filler limits it
        $this->assertSame(3, (int) $b->fresh()->quantity_in_stock);

        // Selling 1 takes 12 roses and 2 of the filler
        $plan = Product::stockPlan([[$b->id, null, 1]]);
        $this->assertSame([[$rose->id, null, 12, $b->id], [$breath->id, null, 2, $b->id]], $plan);
        DB::transaction(function () use ($plan) {
            foreach ($plan as [$id, $color, $qty]) {
                $this->assertNotNull(Product::takeStock($id, $qty, $color));
            }
        });
        $this->assertSame([88, 4], [(int) $rose->fresh()->quantity_in_stock, (int) $breath->fresh()->quantity_in_stock]);

        // The form: a filler row must be one of the shop's fillers, not a flower or a wrapper
        $kraft = Product::forceCreate([
            'owner_id' => 1, 'product_name' => 'Kraft Paper', 'sku' => 'W-2', 'quantity_in_stock' => 9, 'min_stock_level' => 0,
            'stock_kind' => 'wrapping', 'stock_unit' => 'sheet', 'moderation_status' => Product::MODERATION_INTERNAL, 'status' => 'active',
        ]);
        $method = new \ReflectionMethod(ProductController::class, 'compositionFromRequest');
        $method->setAccessible(true);
        $read = fn ($rows) => $method->invoke(app(ProductController::class), Request::create('/', 'POST', ['composition' => json_encode($rows)]), 'bouquet', 1, 999);
        $ok = $read([['flower' => "Baby's breath", 'count' => 2, 'product_id' => $breath->id, 'kind' => 'filler', 'color' => 'ignored']]);
        $this->assertSame(['flower' => "Baby's breath", 'color' => null, 'count' => 2, 'cost' => null, 'product_id' => $breath->id, 'kind' => 'filler'], $ok['attributes']['composition'][0]);
        $this->assertArrayHasKey('errors', $read([['flower' => 'Breath', 'count' => 1, 'kind' => 'filler']]), 'no filler picked');
        $this->assertArrayHasKey('errors', $read([['flower' => 'Rose', 'count' => 1, 'product_id' => $rose->id, 'kind' => 'filler']]), 'a flower is not a filler');
        $this->assertArrayHasKey('errors', $read([['flower' => 'Kraft', 'count' => 1, 'product_id' => $kraft->id, 'kind' => 'filler']]), 'a wrapper is not a filler');
        $this->assertArrayHasKey('errors', $read([['flower' => 'Breath', 'count' => 1, 'product_id' => $breath->id, 'kind' => 'wrapper']]), 'a filler is not a wrapper');

        // Customers don't see the filler in "What's inside"
        $customer = new \ReflectionMethod(\App\Http\Controllers\Customer\CustomerProductController::class, 'composition');
        $customer->setAccessible(true);
        $inside = $customer->invoke(app(\App\Http\Controllers\Customer\CustomerProductController::class), $b->fresh()->composition);
        $this->assertSame(['Rose'], array_column($inside, 'flower'));
    }

    public function test_links_must_be_the_shops_own_single_flowers(): void
    {
        $rose = $this->flower('Red rose', 100);
        $other = $this->bouquet([['flower' => 'Rose', 'count' => 1]]);
        $method = new \ReflectionMethod(ProductController::class, 'compositionFromRequest');
        $method->setAccessible(true);
        $read = fn ($rows, $owner = 1) => $method->invoke(app(ProductController::class), Request::create('/', 'POST', ['composition' => json_encode($rows)]), 'bouquet', $owner, 999);

        $ok = $read([['flower' => 'Rose', 'count' => 12, 'product_id' => $rose->id]]);
        $this->assertSame($rose->id, $ok['attributes']['composition'][0]['product_id']);
        $this->assertArrayHasKey('errors', $read([['flower' => 'Rose', 'count' => 12, 'product_id' => $rose->id]], 2), "another shop's flower");
        $this->assertArrayHasKey('errors', $read([['flower' => 'Rose', 'count' => 1, 'product_id' => $other->id]]), 'a bouquet');
        $this->assertArrayHasKey('errors', $read([['flower' => 'Rose', 'count' => 1, 'product_id' => 999]]), 'itself');
    }

    public function test_a_bouquets_flowers_are_checked(): void
    {
        $method = new \ReflectionMethod(ProductController::class, 'compositionFromRequest');
        $method->setAccessible(true);
        $controller = app(ProductController::class);
        $read = fn ($composition, $type = 'bouquet') => $method->invoke($controller, Request::create('/', 'POST', ['composition' => json_encode($composition)]), $type);

        $ok = $read([['flower' => ' Rose ', 'color' => 'Red', 'count' => '12', 'cost' => '15.5'], ['flower' => '', 'color' => '', 'count' => ''], ['flower' => "Baby's breath", 'count' => 5, 'cost' => '']]);
        $this->assertSame([
            ['flower' => 'Rose', 'color' => 'Red', 'count' => 12, 'cost' => 15.5, 'product_id' => null],
            ['flower' => "Baby's breath", 'color' => null, 'count' => 5, 'cost' => null, 'product_id' => null],
        ], $ok['attributes']['composition'], 'empty rows are skipped; the cost per piece is optional');
        $this->assertArrayHasKey('errors', $read([['flower' => 'Rose', 'count' => 2, 'cost' => -1]]));
        $this->assertArrayHasKey('errors', $read([['flower' => 'Rose', 'count' => 2, 'cost' => 'a lot']]));

        $this->assertSame(['composition' => null], $read([['flower' => 'Rose', 'count' => 1]], 'per_piece')['attributes'], 'only bouquets keep a list');
        $this->assertArrayHasKey('errors', $read([['flower' => '', 'count' => 3]]));
        $this->assertArrayHasKey('errors', $read([['flower' => 'Rose', 'count' => 0]]));
        $this->assertArrayHasKey('errors', $read([['flower' => 'Rose', 'count' => 1.5]]));
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
