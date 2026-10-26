<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesSubscriptionTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Supplier management: type and delivery schedule on the supplier, extra
 * contacts saved with it, the catalog with prices, ratings, and the
 * profile's performance history.
 */
class SupplierManagementTest extends TestCase
{
    use CreatesSubscriptionTables, SignsInWithApiToken;

    private const BASE = '/api/procurement/supply-chain/suppliers';

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
        foreach (['supplier_ratings', 'supplier_products', 'supplier_contacts', 'shipments', 'purchase_orders', 'suppliers', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::enableForeignKeyConstraints();
        $this->createSubscriptionTables();

        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('surname')->nullable();
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role')->default('vendor');
            $t->rememberToken()->nullable();
            $t->string('api_token', 80)->nullable()->unique();
            $t->timestamps();
        });
        Schema::create('suppliers', function ($t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('company_name');
            $t->string('contact_person');
            $t->string('email')->unique();
            $t->string('phone', 20);
            $t->text('address');
            $t->string('status')->default('active');
            $t->string('logo')->nullable();
            $t->timestamps();
        });
        Schema::create('supplier_contacts', function ($t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('supplier_id');
            $t->string('company_name');
            $t->string('contact_person');
            $t->string('email')->unique();
            $t->string('phone', 20);
            $t->text('address');
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('purchase_orders', function ($t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->string('order_number')->nullable();
            $t->string('status')->default('pending');
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('shipments', function ($t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('purchase_order_id');
            $t->date('received_date')->nullable();
            $t->string('status')->default('pending');
        });
        // The new columns and tables come from the real migration
        (require database_path('migrations/2026_10_26_100000_add_supplier_management.php'))->up();

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vendor(): User
    {
        $vendor = User::create(['name' => 'Camille', 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'role' => 'vendor']);
        $this->giveActivePlan($vendor);

        return $vendor;
    }

    private function supplier(User $vendor, array $extra = []): Supplier
    {
        return Supplier::create($extra + [
            'owner_id' => $vendor->id, 'company_name' => 'Bloom Farms', 'contact_person' => 'Ana',
            'email' => fake()->unique()->safeEmail(), 'phone' => '09171234567', 'address' => 'Benguet', 'status' => 'active',
        ]);
    }

    public function test_creating_a_supplier_saves_type_schedule_and_extra_contacts(): void
    {
        $vendor = $this->vendor();

        // The forms send multipart data: contacts and profile as JSON text
        $res = $this->signIn($vendor)->post(self::BASE, [
            'company_name' => 'Bloom Farms', 'contact_person' => 'Ana', 'email' => 'farm@example.com',
            'phone' => '09171234567', 'address' => 'La Trinidad, Benguet',
            'profile' => json_encode([
                'supplier_type' => 'flower_farm', 'delivery_days' => ['Tuesday', 'Friday'], 'lead_time_days' => 3,
                'order_cutoff' => '14:00', 'minimum_order' => 5000, 'payment_terms' => '30 days', 'notes' => null,
            ]),
            'contacts' => json_encode([['contact_person' => 'Ben', 'email' => 'ben@example.com', 'phone' => '0918']]),
        ], ['Accept' => 'application/json'])->assertCreated();

        $supplier = Supplier::findOrFail($res->json('id'));
        $this->assertSame('flower_farm', $supplier->supplier_type);
        $this->assertSame(['Tuesday', 'Friday'], $supplier->delivery_days);
        $this->assertSame(3, $supplier->lead_time_days);
        $this->assertSame('14:00', $supplier->order_cutoff);
        // A contact without its own company or address takes the supplier's
        $contact = $supplier->contacts()->first();
        $this->assertSame('Ben', $contact->contact_person);
        $this->assertSame('Bloom Farms', $contact->company_name);
        $this->assertSame('La Trinidad, Benguet', $contact->address);
    }

    public function test_clearing_a_field_on_edit_clears_it(): void
    {
        $vendor = $this->vendor();
        $supplier = $this->supplier($vendor, ['lead_time_days' => 4, 'supplier_type' => 'wholesaler']);

        $this->signIn($vendor)->post(self::BASE . "/{$supplier->id}", [
            '_method' => 'PUT',
            'profile' => json_encode(['supplier_type' => 'wholesaler', 'lead_time_days' => null, 'delivery_days' => []]),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertNull($supplier->fresh()->lead_time_days);
        $this->assertSame('wholesaler', $supplier->fresh()->supplier_type);
    }

    public function test_the_list_filters_by_type_and_counts_every_supplier(): void
    {
        $vendor = $this->vendor();
        $this->supplier($vendor, ['supplier_type' => 'flower_farm', 'company_name' => 'Rose Farm']);
        $this->supplier($vendor, ['supplier_type' => 'packaging', 'company_name' => 'Wrap Co', 'status' => 'inactive']);
        $this->supplier($vendor, ['company_name' => 'Untyped']);

        $res = $this->signIn($vendor)->getJson(self::BASE . '?type=flower_farm')->assertOk();
        $this->assertSame(['Rose Farm'], array_column($res->json('data'), 'company_name'));
        $this->assertSame(3, $res->json('counts.total'));
        $this->assertSame(1, $res->json('counts.by_status.inactive'));
        $this->assertSame(1, $res->json('counts.by_type.none'));

        // Search ignores capitals
        $this->getJson(self::BASE . '?search=wrap')->assertOk()->assertJsonPath('data.0.company_name', 'Wrap Co');
    }

    public function test_catalog_items_can_be_added_changed_and_removed(): void
    {
        $vendor = $this->vendor();
        $supplier = $this->supplier($vendor);
        $this->signIn($vendor);

        $id = $this->postJson(self::BASE . "/{$supplier->id}/products", [
            'name' => 'Red rose, 50cm', 'category' => 'flowers', 'unit' => 'stem', 'price' => 18.5, 'min_qty' => 100,
        ])->assertCreated()->json('data.id');

        Carbon::setTestNow(Carbon::parse('2026-10-22 09:00:00'));
        $this->putJson(self::BASE . "/{$supplier->id}/products/{$id}", [
            'name' => 'Red rose, 50cm', 'category' => 'flowers', 'unit' => 'stem', 'price' => 20, 'min_qty' => 100, 'is_available' => false,
        ])->assertOk()->assertJsonPath('data.is_available', false);

        $item = DB::table('supplier_products')->find($id);
        $this->assertEquals(20, $item->price);
        $this->assertStringStartsWith('2026-10-22', $item->price_updated_at);

        $this->deleteJson(self::BASE . "/{$supplier->id}/products/{$id}")->assertOk();
        $this->assertSame(0, DB::table('supplier_products')->count());
    }

    public function test_profile_sums_up_ratings_and_order_history(): void
    {
        $vendor = $this->vendor();
        $supplier = $this->supplier($vendor, ['lead_time_days' => 3]);
        $this->signIn($vendor);

        // Two arrived (3 days: on time; 6 days: late), one still open
        $a = DB::table('purchase_orders')->insertGetId(['owner_id' => $vendor->id, 'supplier_id' => $supplier->id, 'status' => 'completed', 'total_amount' => 4000, 'created_at' => '2026-09-01 08:00:00']);
        $b = DB::table('purchase_orders')->insertGetId(['owner_id' => $vendor->id, 'supplier_id' => $supplier->id, 'status' => 'received', 'total_amount' => 2500, 'created_at' => '2026-10-01 08:00:00']);
        DB::table('purchase_orders')->insert(['owner_id' => $vendor->id, 'supplier_id' => $supplier->id, 'status' => 'shipped', 'total_amount' => 1500, 'created_at' => '2026-10-15 08:00:00']);
        DB::table('shipments')->insert([
            ['purchase_order_id' => $a, 'received_date' => '2026-09-04', 'status' => 'delivered'],
            ['purchase_order_id' => $b, 'received_date' => '2026-10-07', 'status' => 'delivered'],
        ]);

        $this->postJson(self::BASE . "/{$supplier->id}/ratings", ['quality' => 5, 'timeliness' => 3, 'communication' => 4, 'purchase_order_id' => $b])->assertCreated();
        $this->postJson(self::BASE . "/{$supplier->id}/ratings", ['quality' => 4, 'timeliness' => 2, 'communication' => 3, 'comment' => 'Late again'])->assertCreated();

        // Another supplier's order can't be linked
        $other = $this->supplier($vendor, ['company_name' => 'Other']);
        $theirs = DB::table('purchase_orders')->insertGetId(['owner_id' => $vendor->id, 'supplier_id' => $other->id, 'status' => 'pending', 'created_at' => '2026-10-10 08:00:00']);
        $this->postJson(self::BASE . "/{$supplier->id}/ratings", ['quality' => 5, 'timeliness' => 5, 'communication' => 5, 'purchase_order_id' => $theirs])->assertStatus(422);

        $d = $this->getJson(self::BASE . "/{$supplier->id}/profile")->assertOk()->json('data');

        $this->assertSame(2, $d['ratings']['count']);
        $this->assertEquals(4.5, $d['ratings']['quality']);
        $this->assertEquals(2.5, $d['ratings']['timeliness']);
        $this->assertEquals(3.5, $d['ratings']['score']);
        $this->assertSame('Camille', $d['ratings']['recent'][0]['rated_by_name']);

        $p = $d['performance'];
        $this->assertSame(3, $p['orders']);
        $this->assertSame(1, $p['open']);
        $this->assertEquals(66.7, $p['fill_rate']);
        $this->assertEquals(4.5, $p['avg_lead_days']);
        $this->assertEquals(50, $p['on_time_rate']);
        $this->assertCount(6, $p['months']);
        $this->assertEquals(['month' => '2026-10', 'orders' => 2, 'value' => 4000, 'arrived' => 1], $p['months'][5]);

        // Ratings show on the list too
        $row = collect($this->getJson(self::BASE)->assertOk()->json('data'))->firstWhere('id', $supplier->id);
        $this->assertEquals(3.5, $row['rating']);
        $this->assertSame(2, $row['ratings_count']);
    }

    public function test_a_supplier_with_orders_cannot_be_deleted_and_says_why(): void
    {
        $vendor = $this->vendor();
        $supplier = $this->supplier($vendor);
        DB::table('purchase_orders')->insert(['owner_id' => $vendor->id, 'supplier_id' => $supplier->id, 'status' => 'completed', 'created_at' => now()]);

        $this->signIn($vendor)->deleteJson(self::BASE . "/{$supplier->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', "This supplier has purchase orders, so it can't be deleted. Deactivate or blacklist it instead to keep its history.");
    }

    public function test_another_shops_supplier_is_out_of_reach(): void
    {
        $vendor = $this->vendor();
        $theirs = $this->supplier($this->vendor());

        $this->signIn($vendor)->getJson(self::BASE . "/{$theirs->id}/profile")->assertNotFound();
        $this->postJson(self::BASE . "/{$theirs->id}/products", ['name' => 'X', 'unit' => 'stem', 'price' => 1])->assertNotFound();
    }
}
