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
 * CRM dashboard: customers (new, returning, repeat, retained), top spenders,
 * inquiries and what's still waiting, review scores, and chat reply times.
 */
class CrmDashboardTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    private const OCTOBER = '?from=2026-10-01&to=2026-10-31';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        foreach (['customer_inquiries', 'product_reviews', 'conversations', 'messages'] as $t) {
            Schema::dropIfExists($t);
        }
        if (! Schema::hasColumn('users', 'profile_picture')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('profile_picture')->nullable());
        }
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('product_name')->nullable();
            $t->softDeletes();
        });
        Schema::create('customer_inquiries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->text('question');
            $t->text('answer')->nullable();
            $t->unsignedBigInteger('answered_by')->nullable();
            $t->timestamp('answered_at')->nullable();
            $t->string('status')->default('open');
            $t->boolean('is_public')->default(false);
            $t->timestamp('customer_seen_at')->nullable();
            $t->timestamps();
        });
        Schema::create('product_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedTinyInteger('rating');
            $t->text('comment')->nullable();
            $t->string('image_path')->nullable();
            $t->boolean('is_anonymous')->default(false);
            $t->timestamp('hidden_at')->nullable();
            $t->string('hidden_reason')->nullable();
            $t->unsignedBigInteger('hidden_by')->nullable();
            $t->timestamps();
        });
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->text('last_message')->nullable();
            $t->timestamp('last_message_time')->nullable();
            $t->unsignedInteger('unread_count_customer')->default(0);
            $t->unsignedInteger('unread_count_vendor')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamp('vendor_archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('conversation_id');
            $t->unsignedBigInteger('sender_id');
            $t->text('message')->nullable();
            $t->string('message_type')->default('text');
            $t->json('attachments')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(string $name): User
    {
        return User::create(['name' => $name, 'surname' => 'Reyes', 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'role' => 'customer']);
    }

    private function buy(User $vendor, User $who, float $amount, string $at, array $extra = []): int
    {
        return $this->order($vendor, $extra + ['user_id' => $who->id, 'total_amount' => $amount, 'paid_at' => $at, 'created_at' => $at]);
    }

    public function test_it_sums_up_customers_issues_satisfaction_and_replies(): void
    {
        $vendor = $this->shopOwner();
        [$ana, $ben, $cy, $dee] = [$this->customer('Ana'), $this->customer('Ben'), $this->customer('Cy'), $this->customer('Dee')];

        // September: Ana and Ben buy. October: Ana again (kept), Cy twice (new, repeat), Dee only cancelled
        $this->buy($vendor, $ana, 1000, '2026-09-10 10:00:00');
        $this->buy($vendor, $ben, 600, '2026-09-12 10:00:00');
        $this->buy($vendor, $ana, 1500, '2026-10-05 10:00:00');
        $this->buy($vendor, $cy, 300, '2026-10-06 10:00:00');
        $this->buy($vendor, $cy, 200, '2026-10-15 10:00:00');
        $this->buy($vendor, $dee, 9999, '2026-10-16 10:00:00', ['status' => 'cancelled']);
        // Another shop's customer stays out
        $other = $this->shopOwner();
        $this->buy($other, $ben, 7000, '2026-10-07 10:00:00');

        $rose = DB::table('products')->insertGetId(['owner_id' => $vendor->id, 'product_name' => 'Rose box']);
        $inq = fn ($who, $status, $asked, $answered = null) => DB::table('customer_inquiries')->insert([
            'vendor_id' => $vendor->id, 'customer_id' => $who->id, 'product_id' => $rose, 'question' => 'Same-day delivery?',
            'status' => $status, 'answered_at' => $answered, 'created_at' => $asked, 'updated_at' => $asked,
        ]);
        $inq($ana, 'answered', '2026-10-02 09:00:00', '2026-10-02 12:00:00');   // 3 h
        $inq($cy, 'answered', '2026-10-03 09:00:00', '2026-10-03 10:00:00');    // 1 h
        $inq($ben, 'open', '2026-10-18 09:00:00');                              // overdue
        $inq($dee, 'open', '2026-10-20 08:00:00');                              // fresh

        DB::table('order_requests')->insert(['owner_id' => $vendor->id, 'order_id' => 3, 'user_id' => $ana->id, 'status' => 'pending', 'created_at' => '2026-10-17 09:00:00', 'updated_at' => '2026-10-17 09:00:00']);
        DB::table('order_requests')->insert(['owner_id' => $vendor->id, 'order_id' => 3, 'user_id' => $ana->id, 'status' => 'completed', 'created_at' => '2026-10-08 09:00:00', 'updated_at' => '2026-10-08 09:00:00']);

        $review = fn ($who, $stars, $at, $hidden = null) => DB::table('product_reviews')->insert(['product_id' => $rose, 'user_id' => $who->id, 'rating' => $stars, 'comment' => 'Lovely', 'hidden_at' => $hidden, 'created_at' => $at, 'updated_at' => $at]);
        $review($ana, 5, '2026-10-06 10:00:00');
        $review($cy, 4, '2026-10-16 10:00:00');
        $review($cy, 2, '2026-10-17 10:00:00');
        $review($ben, 1, '2026-10-18 10:00:00', '2026-10-18 11:00:00'); // hidden
        $review($ben, 3, '2026-09-20 10:00:00');                        // September

        $chat = DB::table('conversations')->insertGetId(['owner_id' => $vendor->id, 'vendor_id' => $vendor->id, 'customer_id' => $ana->id, 'unread_count_vendor' => 2]);
        $msg = fn ($sender, $at) => DB::table('messages')->insert(['owner_id' => $vendor->id, 'conversation_id' => $chat, 'sender_id' => $sender, 'message' => 'hi', 'created_at' => $at, 'updated_at' => $at]);
        $msg($ana->id, '2026-10-10 10:00:00');
        $msg($ana->id, '2026-10-10 10:05:00');     // still waiting from 10:00
        $msg($vendor->id, '2026-10-10 10:30:00');  // 30 min
        $msg($ana->id, '2026-10-11 09:00:00');
        $msg($vendor->id, '2026-10-11 11:30:00');  // 150 min
        $msg($ana->id, '2026-10-19 09:00:00');     // not answered yet

        $res = $this->signIn($vendor)->getJson('/api/crm/dashboard' . self::OCTOBER)->assertOk();

        $res->assertJsonPath('data.customers.total', 3)
            ->assertJsonPath('data.customers.new', 1)
            ->assertJsonPath('data.customers.previous_new', 2)
            ->assertJsonPath('data.customers.active', 2)
            ->assertJsonPath('data.customers.returning', 1)
            ->assertJsonPath('data.customers.repeat', 2)
            ->assertJsonPath('data.customers.retained', 1)
            ->assertJsonPath('data.customers.previous_buyers', 2)
            ->assertJsonPath('data.top_customers.0.name', 'Ana Reyes')
            ->assertJsonPath('data.top_customers.0.orders', 2)
            ->assertJsonPath('data.top_customers.1.name', 'Ben Reyes')
            ->assertJsonPath('data.inquiries.open', 2)
            ->assertJsonPath('data.inquiries.answered', 2)
            ->assertJsonPath('data.inquiries.recent.0.status', 'open')
            ->assertJsonPath('data.issues.unanswered_inquiries', 2)
            ->assertJsonPath('data.issues.overdue_inquiries', 1)
            ->assertJsonPath('data.issues.open_requests', 1)
            ->assertJsonPath('data.issues.unread_chats', 1)
            ->assertJsonPath('data.issues.total', 4)
            ->assertJsonPath('data.satisfaction.count', 3)
            ->assertJsonPath('data.satisfaction.all_time_count', 4)
            ->assertJsonPath('data.satisfaction.previous_average', 3)
            ->assertJsonPath('data.response.replied', 2)
            ->assertJsonPath('data.response.waiting', 1)
            ->assertJsonPath('data.response.avg_reply_minutes', 90);

        $this->assertEquals(2500, $res->json('data.top_customers.0.spent'));
        $this->assertEquals(66.7, $res->json('data.customers.repeat_rate'));
        $this->assertEquals(50, $res->json('data.customers.retention_rate'));
        $this->assertEquals(2, $res->json('data.inquiries.avg_answer_hours'));
        $this->assertEquals(3.67, $res->json('data.satisfaction.average'));
        $this->assertEquals(66.7, $res->json('data.satisfaction.happy_rate'));
        $this->assertEquals(50, $res->json('data.response.within_hour_rate'));
        $this->assertSame([1, 1, 0, 1, 0], array_column($res->json('data.satisfaction.distribution'), 'count'));
    }

    public function test_staff_need_the_crm_dashboard_permission(): void
    {
        $vendor = $this->shopOwner();
        $grant = function (Employee $e, string $module) use ($vendor) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $e->id, 'module' => $module, 'permission' => 'view', 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        };
        $crm = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Cara', 'email' => 'cara@example.com', 'password' => 'x', 'status' => 'Active']);
        $grant($crm, 'crm_dashboard');
        $chatOnly = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Chad', 'email' => 'chad@example.com', 'password' => 'x', 'status' => 'Active']);
        $grant($chatOnly, 'crm');

        $this->signIn($crm)->getJson('/api/crm/dashboard')->assertOk()->assertJsonPath('data.customers.total', 0);
        $this->app['auth']->forgetGuards();
        $this->signIn($chatOnly)->getJson('/api/crm/dashboard')->assertForbidden();
    }
}
