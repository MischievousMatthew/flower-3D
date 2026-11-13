<?php

namespace Tests\Feature;

use App\Exceptions\PromotionUnavailableException;
use App\Models\Product;
use App\Models\VendorPromotion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A promotion can be limited to a number of people, each once: the spot is
 * taken in the order's transaction (never past the limit, never below 0) and
 * given back when the order is cancelled.
 */
class PromotionUsageLimitTest extends TestCase
{
    private int $shop;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['promotion_redemptions', 'vendor_promotions', 'products', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
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
        (include database_path('migrations/2026_11_14_100000_add_usage_limit_to_vendor_promotions.php'))->up();
        VendorPromotion::forgetCache();

        $this->shop = DB::table('users')->insertGetId(['name' => 'Petal']);
    }

    private function promotion(?int $maxUses): VendorPromotion
    {
        return VendorPromotion::query()->forceCreate([
            'owner_id' => $this->shop, 'name' => "Valentine's", 'discount_type' => 'percent', 'discount_value' => 20,
            'applies_to' => 'all', 'targets' => null, 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(),
            'is_active' => true, 'max_uses' => $maxUses,
        ]);
    }

    private function customer(string $name): int
    {
        return DB::table('users')->insertGetId(['name' => $name]);
    }

    public function test_the_limit_is_never_passed_and_each_customer_uses_it_once(): void
    {
        $promotion = $this->promotion(2);
        [$ana, $ben, $cy] = [$this->customer('Ana'), $this->customer('Ben'), $this->customer('Cy')];

        VendorPromotion::claim([$promotion->id], $ana, 101);
        VendorPromotion::claim([$promotion->id], $ben, 102);
        $this->assertSame(2, (int) $promotion->fresh()->uses_count);
        $this->assertSame('full', $promotion->fresh()->state());

        // The third person is refused and nothing changes
        try {
            DB::transaction(fn () => VendorPromotion::claim([$promotion->id], $cy, 103));
            $this->fail('the limit was passed');
        } catch (PromotionUnavailableException $e) {
            $this->assertStringContainsString('just ran out', $e->getMessage());
        }
        $this->assertSame(2, (int) $promotion->fresh()->uses_count);
        $this->assertSame(2, DB::table('promotion_redemptions')->count());
    }

    public function test_the_same_customer_cant_use_it_twice_and_the_count_is_put_back(): void
    {
        $promotion = $this->promotion(5);
        $ana = $this->customer('Ana');
        VendorPromotion::claim([$promotion->id], $ana, 101);

        try {
            DB::transaction(fn () => VendorPromotion::claim([$promotion->id], $ana, 102));
            $this->fail('used twice');
        } catch (PromotionUnavailableException $e) {
            $this->assertStringContainsString('already used', $e->getMessage());
        }
        // The order's transaction rolled back, so its count went back too
        $this->assertSame(1, (int) $promotion->fresh()->uses_count);
    }

    public function test_a_cancelled_order_gives_its_spot_back_once_and_the_count_never_goes_below_zero(): void
    {
        $promotion = $this->promotion(1);
        $ana = $this->customer('Ana');
        VendorPromotion::claim([$promotion->id], $ana, 101);

        VendorPromotion::release(101);
        VendorPromotion::release(101); // a retry or double click
        $this->assertSame(0, (int) $promotion->fresh()->uses_count);
        $this->assertSame('running', $promotion->fresh()->state());

        // Even if the count was already 0 somehow, giving back never makes it -1
        DB::table('promotion_redemptions')->insert(['promotion_id' => $promotion->id, 'user_id' => $ana, 'order_id' => 200, 'created_at' => now(), 'updated_at' => now()]);
        VendorPromotion::release(200);
        $this->assertSame(0, (int) $promotion->fresh()->uses_count);

        // The freed spot can be taken again
        VendorPromotion::claim([$promotion->id], $this->customer('Ben'), 102);
        $this->assertSame(1, (int) $promotion->fresh()->uses_count);
    }

    public function test_customers_see_the_regular_price_once_its_full_or_they_used_it(): void
    {
        $promotion = $this->promotion(1);
        $productId = DB::table('products')->insertGetId(['owner_id' => $this->shop, 'product_name' => 'Rose', 'selling_price' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $price = function () use ($productId) {
            VendorPromotion::forgetCache();

            return Product::withoutGlobalScopes()->find($productId)->discount_price;
        };
        $ana = $this->customer('Ana');

        $this->assertSame('80.00', $price(), '20% off while there are spots');

        // Ana used it: she no longer gets it, and with its only spot taken nobody does
        VendorPromotion::claim([$promotion->id], $ana, 101);
        Auth::loginUsingId($ana);
        $this->assertNull($price());
        Auth::logout();
        $this->assertNull($price());

        // No limit: everyone gets it (once each)
        $promotion->update(['max_uses' => null]);
        $this->assertSame('80.00', $price());
    }

    public function test_the_limit_cant_be_set_below_the_people_who_used_it(): void
    {
        $promotion = $this->promotion(5);
        foreach (['Ana', 'Ben', 'Cy'] as $i => $name) {
            VendorPromotion::claim([$promotion->id], $this->customer($name), 100 + $i);
        }
        $this->assertSame(2, $promotion->fresh()->spotsLeft());

        $request = \Illuminate\Http\Request::create("/api/vendor/promotions/{$promotion->id}", 'PUT', [
            'name' => "Valentine's", 'discount_type' => 'percent', 'discount_value' => 20, 'applies_to' => 'all',
            'starts_at' => now()->subHour()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String(), 'max_uses' => 2,
        ]);
        $method = new \ReflectionMethod(\App\Http\Controllers\VendorPromotionController::class, 'validated');

        try {
            $method->invoke(app(\App\Http\Controllers\VendorPromotionController::class), $request, $this->shop, $promotion->fresh());
            $this->fail('the limit went below the people who used it');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('3 people already used it', $e->errors()['max_uses'][0]);
        }
    }
}
