<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Segments come from purchase history and customer-approved preferences;
 * offers reach only customers who said yes, haven't muted the shop, and
 * haven't had one from the shop in the last few days.
 */
class CustomerSegmentsTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        foreach (['conversations', 'customer_inquiries'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->timestamps();
        });
        Schema::create('customer_inquiries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->text('question');
            $t->string('status')->default('open');
            $t->timestamps();
        });
        foreach (['customer_offer_recipients', 'customer_offers', 'favorite_products', 'customer_notes', 'vendor_customer_profiles', 'customer_dates', 'customer_preferences', 'vendor_promotions'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('vendor_applications', fn (Blueprint $t) => $t->string('store_name')->nullable());
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('product_name')->nullable();
            $t->json('occasion_tags')->nullable();
            $t->softDeletes();
        });
        Schema::create('vendor_promotions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('name');
            $t->string('discount_type');
            $t->decimal('discount_value', 10, 2);
            $t->string('applies_to')->default('all');
            $t->json('targets')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_24_100000_create_customer_management.php'))->up();
        (require base_path('database/migrations/2026_10_24_400000_create_customer_offers.php'))->up();
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function as($account)
    {
        $this->app['auth']->forgetGuards();

        return $this->signIn($account);
    }

    private function customer(string $name): User
    {
        return User::create(['name' => $name, 'surname' => 'Reyes', 'email' => strtolower($name) . '@example.com', 'password' => 'x', 'role' => 'customer']);
    }

    private function buy(User $shop, User $who, string $at, ?int $productId = null, float $amount = 1000): void
    {
        $orderId = $this->order($shop, ['user_id' => $who->id, 'total_amount' => $amount, 'paid_at' => $at, 'created_at' => $at]);
        if ($productId) {
            DB::table('order_items')->insert(['order_id' => $orderId, 'product_id' => $productId, 'product_name' => 'Bridal bouquet', 'quantity' => 1, 'unit_price' => $amount, 'subtotal' => $amount]);
        }
    }

    private function optIn(User $who, bool $share = false, array $occasions = []): void
    {
        DB::table('customer_preferences')->insert(['user_id' => $who->id, 'accept_offers' => true, 'share_with_shops' => $share, 'occasions' => json_encode($occasions), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_segments_follow_purchase_history_and_approved_preferences(): void
    {
        $shop = $this->shopOwner();
        DB::table('vendor_applications')->where('email', $shop->email)->update(['store_name' => 'Petal & Stem']);
        $bridal = DB::table('products')->insertGetId(['owner_id' => $shop->id, 'product_name' => 'Bridal bouquet', 'occasion_tags' => json_encode(['Wedding'])]);
        [$ana, $ben, $cy, $dee, $eve] = array_map(fn ($n) => $this->customer($n), ['Ana', 'Ben', 'Cy', 'Dee', 'Eve']);

        $this->buy($shop, $ana, '2026-10-10 10:00:00');                                  // new
        foreach (['2026-06-01', '2026-07-01', '2026-08-01', '2026-09-01'] as $d) {
            $this->buy($shop, $ben, "{$d} 10:00:00");                                       // repeat + frequent
        }
        $this->buy($shop, $cy, '2026-03-01 10:00:00', $bridal);                         // events (bought) + inactive
        $this->buy($shop, $dee, '2026-09-15 10:00:00');                                 // events only via shared prefs
        $this->optIn($dee, share: true, occasions: ['Wedding']);
        $this->buy($shop, $eve, '2026-09-01 10:00:00');                                 // says weddings but doesn't share: not counted
        DB::table('customer_preferences')->insert(['user_id' => $eve->id, 'accept_offers' => false, 'share_with_shops' => false, 'occasions' => json_encode(['Wedding']), 'created_at' => now(), 'updated_at' => now()]);
        $this->optIn($ana);
        $this->optIn($cy);

        $res = $this->as($shop)->getJson('/api/crm/segments')->assertOk();
        $by = collect($res->json('data.segments'))->keyBy('key');
        $this->assertSame(5, $res->json('data.customers'));
        $this->assertSame(1, $by['new']['count']);
        $this->assertSame(1, $by['repeat']['count']);
        $this->assertSame(1, $by['frequent']['count']);
        $this->assertSame(2, $by['events']['count']);
        $this->assertSame(1, $by['inactive']['count']);
        $this->assertSame(2, $by['events']['reachable']);   // Cy and Dee said yes
        $this->assertSame(0, $by['repeat']['reachable']);   // Ben never did

        $events = $this->getJson('/api/crm/segments/events')->assertOk()->json('data.rows');
        $this->assertEqualsCanonicalizing(['Cy Reyes', 'Dee Reyes'], array_column($events, 'name'));
        $this->assertSame(['Bought Bridal bouquet'], collect($events)->firstWhere('name', 'Cy Reyes')['why']);
        $this->getJson('/api/crm/segments/nope')->assertNotFound();
    }

    public function test_consent_at_checkout_and_the_one_time_question(): void
    {
        $shop = $this->shopOwner();
        $ana = $this->customer('Ana');
        $ben = $this->customer('Ben');

        // No order yet: nobody asks
        $this->as($ana)->getJson('/api/customer/preferences')->assertOk()->assertJsonPath('data.offers_prompt', false);

        // An unticked box at checkout changes nothing, and the question still comes after the order
        $this->buy($shop, $ana, '2026-10-19 10:00:00');
        $this->postJson('/api/customer/offers/consent', ['accept' => false, 'source' => 'checkout'])->assertOk()
            ->assertJsonPath('data.accept_offers', false)->assertJsonPath('data.offers_prompt', true);

        // "Not now" stops the question for good
        $this->postJson('/api/customer/offers/consent', ['accept' => false, 'source' => 'prompt'])->assertOk()
            ->assertJsonPath('data.offers_prompt', false)->assertJsonPath('data.accept_offers', false);

        // A ticked box is a yes, kept with when and where it was given
        $this->as($ben)->postJson('/api/customer/offers/consent', ['accept' => true, 'source' => 'checkout'])->assertOk()
            ->assertJsonPath('data.accept_offers', true)->assertJsonPath('data.offers_prompt', false);
        $row = DB::table('customer_preferences')->where('user_id', $ben->id)->first();
        $this->assertSame('checkout', $row->offers_consent_source);
        $this->assertNotNull($row->offers_consent_at);

        // Saying yes again later keeps the first record
        $this->postJson('/api/customer/offers/consent', ['accept' => true, 'source' => 'prompt'])->assertOk();
        $this->assertSame('checkout', DB::table('customer_preferences')->where('user_id', $ben->id)->value('offers_consent_source'));

        // Shop accounts can't answer for customers
        $this->as($shop)->postJson('/api/customer/offers/consent', ['accept' => true, 'source' => 'prompt'])->assertForbidden();
    }

    public function test_offers_reach_only_willing_customers_who_can_see_and_mute_them(): void
    {
        $shop = $this->shopOwner();
        DB::table('vendor_applications')->where('email', $shop->email)->update(['store_name' => 'Petal & Stem']);
        $promo = DB::table('vendor_promotions')->insertGetId(['owner_id' => $shop->id, 'name' => 'Wedding season', 'discount_type' => 'percent', 'discount_value' => 15, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'created_at' => now(), 'updated_at' => now()]);
        [$ana, $ben, $cy] = array_map(fn ($n) => $this->customer($n), ['Ana', 'Ben', 'Cy']);
        foreach ([$ana, $ben, $cy] as $c) {
            $this->buy($shop, $c, '2026-01-10 10:00:00');   // all inactive
        }
        $this->optIn($ana);
        $this->optIn($cy);

        // Cy mutes the shop from their side
        $this->as($cy)->postJson("/api/customer/offers/shops/{$shop->id}")->assertOk()->assertJsonPath('data.muted_shops.0.shop', 'Petal & Stem');

        $writer = Employee::forceCreate(['owner_id' => $shop->id, 'name' => 'Mara', 'email' => 'mara@example.com', 'password' => 'x', 'status' => 'Active']);
        DB::table('employee_module_permissions')->insert(['owner_id' => $shop->id, 'employee_id' => $writer->id, 'module' => 'crm_segments', 'permission' => 'create', 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        $offer = ['title' => 'We miss you!', 'body' => '15% off any bouquet this month, just for you.', 'promotion_id' => $promo];
        $this->as($writer)->postJson('/api/crm/segments/inactive/offers', $offer)->assertCreated()
            ->assertJsonPath('data.recipients', 1)->assertJsonPath('data.sent_by', 'Mara');
        // Not again within a few days
        $this->postJson('/api/crm/segments/inactive/offers', $offer)->assertStatus(422);

        $this->assertDatabaseHas('user_notifications', ['user_id' => $ana->id, 'type' => 'offer', 'title' => 'Petal & Stem: We miss you!']);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $ben->id, 'type' => 'offer']);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $cy->id, 'type' => 'offer']);

        // Ana sees it with the promotion, and it's marked seen
        $list = $this->as($ana)->getJson('/api/customer/offers')->assertOk();
        $list->assertJsonPath('data.0.shop', 'Petal & Stem')->assertJsonPath('data.0.promotion.label', '15% off')->assertJsonPath('data.0.new', true);
        $this->assertNotNull(DB::table('customer_offer_recipients')->where('user_id', $ana->id)->value('seen_at'));

        // Saying no stops future offers; preferences stay as they were
        $this->putJson('/api/customer/preferences', ['share_with_shops' => false, 'accept_offers' => false])->assertOk()->assertJsonPath('data.accept_offers', false);

        // Staff without the permission can't send
        $viewer = Employee::forceCreate(['owner_id' => $shop->id, 'name' => 'Vic', 'email' => 'vic@example.com', 'password' => 'x', 'status' => 'Active']);
        DB::table('employee_module_permissions')->insert(['owner_id' => $shop->id, 'employee_id' => $viewer->id, 'module' => 'crm_segments', 'permission' => 'view', 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        $this->as($viewer)->postJson('/api/crm/segments/inactive/offers', $offer)->assertForbidden();
    }
}
