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
 * Customer management both ways: customers keep their preferences, dates and
 * favorites and choose whether shops see them; shops keep their own record and
 * internal notes; customers can see and remove what a shop saved, never the notes.
 */
class CustomerManagementTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        foreach (['favorite_products', 'customer_notes', 'vendor_customer_profiles', 'customer_dates', 'customer_preferences', 'customer_inquiries', 'conversations'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $t) {
            foreach (['contact_number', 'address', 'city', 'region', 'profile_picture'] as $c) {
                if (! Schema::hasColumn('users', $c)) {
                    $t->string($c)->nullable();
                }
            }
        });
        Schema::table('vendor_applications', function (Blueprint $t) {
            $t->string('store_name')->nullable();
            $t->string('business_name')->nullable();
        });
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->string('product_name')->nullable();
            $t->decimal('price', 12, 2)->nullable();
            $t->decimal('selling_price', 12, 2)->nullable();
            $t->softDeletes();
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('product_id');
            $t->string('image_url')->nullable();
            $t->string('image_path')->nullable();
            $t->boolean('is_primary')->default(false);
            $t->integer('display_order')->default(0);
            $t->timestamps();
        });
        Schema::create('customer_inquiries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->text('question');
            $t->string('status')->default('open');
            $t->timestamps();
        });
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_24_100000_create_customer_management.php'))->up();
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(string $name = 'Ana'): User
    {
        return User::create(['name' => $name, 'surname' => 'Reyes', 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'role' => 'customer', 'contact_number' => '09171234567']);
    }

    private function shop(string $store): User
    {
        $vendor = $this->shopOwner();
        DB::table('vendor_applications')->where('email', $vendor->email)->update(['store_name' => $store]);

        return $vendor;
    }

    private function staff(User $vendor, array $perms): Employee
    {
        $e = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Cara', 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'status' => 'Active']);
        foreach ($perms as $perm) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $e->id, 'module' => 'crm_customers', 'permission' => $perm, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $e;
    }

    private function as($account)
    {
        $this->app['auth']->forgetGuards();

        return $this->signIn($account);
    }

    public function test_what_a_customer_shares_reaches_their_shops_only_when_they_allow_it(): void
    {
        $shop = $this->shop('Petal & Stem');
        $ana = $this->customer();
        $this->order($shop, ['user_id' => $ana->id, 'total_amount' => 1200, 'paid_at' => '2026-10-02 10:00:00']);
        $rose = DB::table('products')->insertGetId(['owner_id' => $shop->id, 'vendor_id' => $shop->id, 'product_name' => 'Rose box', 'selling_price' => 1200]);

        $this->as($ana)->putJson('/api/customer/preferences', [
            'flowers' => ['Tulips', ' tulips ', 'Peonies'], 'colors' => ['Pastel'], 'avoid' => ['Lilies'], 'occasions' => ['Birthday'],
            'note' => 'Allergic to strong scents', 'share_with_shops' => false,
        ])->assertOk()->assertJsonPath('data.flowers', ['Tulips', 'Peonies']);
        $this->postJson('/api/customer/preferences/dates', ['kind' => 'birthday', 'label' => 'My birthday', 'month' => 10, 'day' => 25, 'year' => 1998])
            ->assertCreated()->assertJsonPath('data.dates.0.days_away', 5)->assertJsonPath('data.dates.0.turning', 28);
        $this->postJson('/api/customer/preferences/dates', ['kind' => 'other', 'label' => 'Nope', 'month' => 2, 'day' => 31])->assertStatus(422);
        $this->postJson("/api/customer/favorites/{$rose}")->assertOk();

        // Kept private: the shop sees none of it
        $this->as($shop)->getJson("/api/crm/customers/{$ana->id}/profile")->assertOk()
            ->assertJsonPath('data.shared', null)->assertJsonPath('data.hearted', [])
            ->assertJsonPath('data.contact.phone', '09171234567')->assertJsonPath('data.stats.orders', 1);

        // Shared: now it does, birthday included
        $this->as($ana)->putJson('/api/customer/preferences', ['flowers' => ['Tulips'], 'share_with_shops' => true])->assertOk();
        $this->as($shop)->getJson("/api/crm/customers/{$ana->id}/profile")->assertOk()
            ->assertJsonPath('data.shared.flowers', ['Tulips'])
            ->assertJsonPath('data.shared.dates.0.label', 'My birthday')
            ->assertJsonPath('data.hearted.0.name', 'Rose box');
        $this->getJson('/api/crm/customers?filter=upcoming')->assertOk()
            ->assertJsonPath('data.counts.upcoming', 1)->assertJsonPath('data.upcoming.0.label', 'My birthday');

        // A shop they never dealt with can't open them
        $other = $this->shop('Other shop');
        $this->as($other)->getJson("/api/crm/customers/{$ana->id}/profile")->assertNotFound();
        $this->getJson('/api/crm/customers')->assertOk()->assertJsonPath('data.counts.all', 0);
    }

    public function test_shops_keep_their_own_record_the_customer_can_see_and_remove_but_notes_stay_internal(): void
    {
        $shop = $this->shop('Petal & Stem');
        $ana = $this->customer();
        DB::table('conversations')->insert(['owner_id' => $shop->id, 'vendor_id' => $shop->id, 'customer_id' => $ana->id]);
        $tulips = DB::table('products')->insertGetId(['owner_id' => $shop->id, 'vendor_id' => $shop->id, 'product_name' => 'Tulip bunch']);
        $notOurs = DB::table('products')->insertGetId(['owner_id' => 999, 'vendor_id' => 999, 'product_name' => 'Elsewhere']);

        $this->as($shop)->putJson("/api/crm/customers/{$ana->id}/preferences", ['flowers' => ['Sunflowers'], 'favorite_product_ids' => [$tulips, $notOurs]])
            ->assertOk()->assertJsonPath('data.shop.flowers', ['Sunflowers'])
            ->assertJsonPath('data.shop.favorite_products', [['id' => $tulips, 'name' => 'Tulip bunch']]);
        $this->postJson("/api/crm/customers/{$ana->id}/dates", ['kind' => 'anniversary', 'label' => 'Wedding anniversary', 'month' => 11, 'day' => 3])->assertCreated();
        $this->postJson("/api/crm/customers/{$ana->id}/notes", ['body' => 'Prefers deliveries after 5pm'])->assertCreated()
            ->assertJsonPath('data.notes.0.author', 'Shop Owner (owner)');

        // The customer sees what the shop saved, without the internal note
        $mine = $this->as($ana)->getJson('/api/customer/preferences')->assertOk();
        $mine->assertJsonPath('data.shops.0.shop', 'Petal & Stem')
            ->assertJsonPath('data.shops.0.flowers', ['Sunflowers'])
            ->assertJsonPath('data.shops.0.dates.0.label', 'Wedding anniversary');
        $this->assertStringNotContainsString('after 5pm', $mine->getContent());

        // …and can remove it
        $this->deleteJson("/api/customer/preferences/shops/{$shop->id}")->assertOk()->assertJsonPath('data.shops', []);
        $this->as($shop)->getJson("/api/crm/customers/{$ana->id}/profile")->assertOk()
            ->assertJsonPath('data.shop.flowers', [])->assertJsonPath('data.shop.dates', [])
            ->assertJsonPath('data.notes.0.body', 'Prefers deliveries after 5pm');
    }

    public function test_staff_permissions_and_customer_only_settings(): void
    {
        $shop = $this->shop('Petal & Stem');
        $ana = $this->customer();
        $this->order($shop, ['user_id' => $ana->id, 'total_amount' => 500, 'paid_at' => '2026-10-02 10:00:00']);

        $viewer = $this->staff($shop, ['view']);
        $this->as($viewer)->getJson("/api/crm/customers/{$ana->id}/profile")->assertOk();
        $this->postJson("/api/crm/customers/{$ana->id}/notes", ['body' => 'Hi'])->assertForbidden();

        $writer = $this->staff($shop, ['view', 'create']);
        $this->as($writer)->postJson("/api/crm/customers/{$ana->id}/notes", ['body' => 'Asked about Valentine pre-orders'])->assertCreated()
            ->assertJsonPath('data.notes.0.author', 'Cara');
        $note = DB::table('customer_notes')->value('id');
        $this->deleteJson("/api/crm/customers/{$ana->id}/notes/{$note}")->assertForbidden();

        // Shop accounts don't have customer preferences
        $this->as($shop)->getJson('/api/customer/preferences')->assertForbidden();
    }
}
