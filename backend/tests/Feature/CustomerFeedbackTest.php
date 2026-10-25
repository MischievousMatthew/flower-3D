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
 * Reviews & feedback in the ERP: the shop's reviews and replies for staff,
 * satisfaction month by month, and complaints that keep coming up across
 * reviews and support cases.
 */
class CustomerFeedbackTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        foreach (['product_reviews', 'support_messages', 'support_tickets', 'notification_preferences', 'product_images'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('vendor_applications', fn (Blueprint $t) => $t->string('store_name')->nullable());
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('product_name')->nullable();
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
        Schema::create('product_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedTinyInteger('rating');
            $t->text('comment')->nullable();
            $t->text('vendor_reply')->nullable();
            $t->timestamp('vendor_replied_at')->nullable();
            $t->string('image_path')->nullable();
            $t->boolean('is_anonymous')->default(false);
            $t->timestamp('hidden_at')->nullable();
            $t->string('hidden_reason')->nullable();
            $t->unsignedBigInteger('hidden_by')->nullable();
            $t->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->unsignedBigInteger('requester_id');
            $t->string('requester_role', 20);
            $t->string('subject', 160);
            $t->string('category', 30);
            $t->string('priority', 20)->default('normal');
            $t->string('status', 20)->default('open');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->timestamp('last_activity_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ticket_id');
            $t->unsignedBigInteger('author_id')->nullable();
            $t->boolean('from_admin')->default(false);
            $t->text('body');
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_24_300000_add_shop_cases_to_support_tickets.php'))->up();
        // What the reviews page waits for before it switches on
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->primary();
            $t->json('settings');
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });
        if (! Schema::hasColumn('user_notifications', 'event_key')) {
            Schema::table('user_notifications', fn (Blueprint $t) => $t->string('event_key', 120)->nullable());
        }
        if (! Schema::hasTable('customer_inquiries')) {
            Schema::create('customer_inquiries', fn (Blueprint $t) => $t->id());
        }
        (new \ReflectionProperty(\App\Services\VendorNotificationService::class, 'ready'))->setValue(null, null);
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

    public function test_trends_satisfaction_and_recurring_complaints(): void
    {
        $shop = $this->shopOwner();
        $ana = User::create(['name' => 'Ana', 'surname' => 'Reyes', 'email' => 'ana@example.com', 'password' => 'x', 'role' => 'customer']);
        $rose = DB::table('products')->insertGetId(['owner_id' => $shop->id, 'product_name' => 'Rose box']);
        $review = fn ($stars, $comment, $at, $reply = null) => DB::table('product_reviews')->insertGetId([
            'product_id' => $rose, 'user_id' => $ana->id, 'rating' => $stars, 'comment' => $comment, 'vendor_reply' => $reply, 'created_at' => $at, 'updated_at' => $at,
        ]);
        $review(5, 'Gorgeous and fresh!', '2026-08-10 10:00:00');
        $review(2, 'Roses were wilted when they arrived', '2026-09-05 10:00:00', 'So sorry, we will replace them.');
        $review(1, 'Half the flowers were dead and the box was crushed', '2026-10-10 10:00:00');
        $review(3, 'Nice but it arrived late', '2026-10-12 10:00:00');
        $review(1, 'Wilted', '2026-02-01 10:00:00');   // before the 6-month window

        DB::table('support_tickets')->insert([
            'reference' => 'SUP-AAA111', 'requester_id' => $ana->id, 'requester_role' => 'customer', 'vendor_id' => $shop->id,
            'subject' => 'Delivery was two hours late', 'category' => 'delivery', 'created_at' => '2026-10-15 10:00:00', 'updated_at' => '2026-10-15 10:00:00',
        ]);

        $res = $this->as($shop)->getJson('/api/crm/feedback?months=6')->assertOk();
        $this->assertCount(6, $res->json('data.trend'));
        $this->assertSame('2026-10', $res->json('data.trend.5.key'));
        $this->assertSame(2, $res->json('data.trend.5.reviews'));
        $this->assertSame(1, $res->json('data.trend.5.cases'));

        $this->assertSame(4, $res->json('data.satisfaction.count'));
        $this->assertEquals(25, $res->json('data.satisfaction.happy_rate'));
        $this->assertEquals(33.3, $res->json('data.satisfaction.answered_rate'));
        $this->assertSame(1, $res->json('data.satisfaction.unanswered_low'));

        $themes = collect($res->json('data.themes'))->keyBy('key');
        $this->assertSame(2, $themes['freshness']['count']);          // wilted + dead
        $this->assertTrue($themes['freshness']['recurring']);
        $this->assertSame(['Rose box'], $themes['freshness']['products']);
        $this->assertSame(2, $themes['late']['count']);               // the review and the case
        $this->assertSame(1, $themes['late']['cases']);
        $this->assertSame(1, $themes['damaged']['count']);
        $this->assertFalse($themes['damaged']['recurring']);
    }

    public function test_staff_see_reviews_and_reply_with_the_right_permission(): void
    {
        $shop = $this->shopOwner();
        $ana = User::create(['name' => 'Ana', 'surname' => 'Reyes', 'email' => 'ana@example.com', 'password' => 'x', 'role' => 'customer']);
        $rose = DB::table('products')->insertGetId(['owner_id' => $shop->id, 'product_name' => 'Rose box']);
        $id = DB::table('product_reviews')->insertGetId(['product_id' => $rose, 'user_id' => $ana->id, 'rating' => 2, 'comment' => 'Wilted', 'created_at' => now(), 'updated_at' => now()]);

        $staff = function (array $perms) use ($shop) {
            $e = Employee::forceCreate(['owner_id' => $shop->id, 'name' => 'Mara', 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'status' => 'Active']);
            foreach ($perms as $p) {
                DB::table('employee_module_permissions')->insert(['owner_id' => $shop->id, 'employee_id' => $e->id, 'module' => 'crm_reviews', 'permission' => $p, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
            }

            return $e;
        };

        $reader = $staff(['view']);
        $this->as($reader)->getJson('/api/crm/reviews')->assertOk()->assertJsonPath('data.reviews.0.comment', 'Wilted');
        $this->postJson("/api/crm/reviews/{$id}/reply", ['reply' => 'Sorry!'])->assertForbidden();

        $writer = $staff(['view', 'create']);
        $this->as($writer)->postJson("/api/crm/reviews/{$id}/reply", ['reply' => 'So sorry, a fresh box is on the way.'])->assertOk();
        $this->assertSame('So sorry, a fresh box is on the way.', DB::table('product_reviews')->where('id', $id)->value('vendor_reply'));
        // The customer is told, and sees it on the product page
        $this->assertDatabaseHas('user_notifications', ['user_id' => $ana->id, 'type' => 'review_reply']);
    }
}
