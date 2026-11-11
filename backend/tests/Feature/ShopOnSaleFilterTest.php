<?php

namespace Tests\Feature;

use App\Http\Controllers\Customer\CustomerProductController;
use App\Models\Product;
use App\Models\VendorPromotion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Shop page's "Sales" link: which flowers count as on sale right now.
 * The products endpoint needs many tables, so this checks the selection it
 * uses (CustomerProductController::onSaleIds) on just the ones it reads.
 */
class ShopOnSaleFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['vendor_promotions', 'products', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->boolean('is_suspended')->default(false);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('product_name');
            $t->string('category')->nullable();
            $t->json('occasion_tags')->nullable();
            $t->decimal('selling_price', 10, 2)->nullable();
            $t->decimal('discount_price', 10, 2)->nullable();
            $t->timestamp('discount_starts_at')->nullable();
            $t->timestamp('discount_ends_at')->nullable();
            $t->string('moderation_status')->default('approved');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('vendor_promotions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('name');
            $t->string('discount_type');
            $t->decimal('discount_value', 10, 2);
            $t->string('applies_to');
            $t->json('targets')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        VendorPromotion::forgetCache();
    }

    private function product(int $ownerId, string $name, array $attrs = []): void
    {
        DB::table('products')->insert($attrs + ['owner_id' => $ownerId, 'product_name' => $name, 'selling_price' => 100, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function onSale(): array
    {
        $method = new \ReflectionMethod(CustomerProductController::class, 'onSaleIds');
        $ids = $method->invoke(new CustomerProductController(), Product::listedForCustomers());

        return Product::whereIn('id', $ids)->orderBy('product_name')->pluck('product_name')->all();
    }

    public function test_only_flowers_selling_below_their_price_right_now(): void
    {
        $shop = DB::table('users')->insertGetId(['name' => 'Petal']);
        $this->product($shop, 'Rose on discount', ['discount_price' => 80]);
        $this->product($shop, 'Tulip full price');
        $this->product($shop, 'Lily discount ended', ['discount_price' => 70, 'discount_ends_at' => now()->subDay()]);
        $this->product($shop, 'Daisy discount not started', ['discount_price' => 70, 'discount_starts_at' => now()->addDay()]);
        $this->product($shop, 'Iris discount above price', ['discount_price' => 120]);

        // A running promotion from another shop puts its covered flowers on sale
        $other = DB::table('users')->insertGetId(['name' => 'Bloom']);
        $this->product($other, 'Orchid in the promotion', ['category' => 'orchids']);
        $this->product($other, 'Fern outside the promotion', ['category' => 'greens']);
        DB::table('vendor_promotions')->insert([
            'owner_id' => $other, 'name' => 'Orchid week', 'discount_type' => 'percent', 'discount_value' => 10,
            'applies_to' => 'category', 'targets' => json_encode(['orchids']),
            'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => true,
        ]);

        $this->assertSame(['Orchid in the promotion', 'Rose on discount'], $this->onSale());
    }

    public function test_nothing_on_sale_is_an_empty_list(): void
    {
        $shop = DB::table('users')->insertGetId(['name' => 'Petal']);
        $this->product($shop, 'Tulip full price');

        $this->assertSame([], $this->onSale());
    }
}
