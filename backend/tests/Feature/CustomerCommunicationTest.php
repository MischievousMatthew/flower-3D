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
 * Customer communication: the shop sends order updates and products into a
 * chat as cards the customer sees (and is notified about), staff reply to
 * product questions from the ERP, and the inbox shows unread and waiting chats.
 */
class CustomerCommunicationTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        foreach (['messages', 'conversations', 'customer_inquiries', 'product_images'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $t) {
            foreach (['contact_number', 'profile_picture', 'username'] as $c) {
                if (! Schema::hasColumn('users', $c)) {
                    $t->string($c)->nullable();
                }
            }
        });
        Schema::table('vendor_applications', function (Blueprint $t) {
            $t->string('store_name')->nullable();
        });
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('product_name')->nullable();
            $t->decimal('selling_price', 12, 2)->nullable();
            $t->softDeletes();
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('product_id');
            $t->string('image_url')->nullable();
            $t->boolean('is_primary')->default(false);
            $t->integer('display_order')->default(0);
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
            foreach (['customer_cleared_at', 'vendor_cleared_at', 'customer_archived_at', 'vendor_archived_at'] as $c) {
                $t->timestamp($c)->nullable();
            }
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
        (require base_path('database/migrations/2026_10_24_200000_add_meta_to_messages.php'))->up();
        Schema::create('customer_inquiries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->text('question');
            $t->text('answer')->nullable();
            $t->string('answered_by', 120)->nullable();
            $t->timestamp('answered_at')->nullable();
            $t->string('status', 12)->default('open');
            $t->boolean('is_public')->default(true);
            $t->timestamp('customer_seen_at')->nullable();
            $t->timestamps();
        });
        // What the inquiry pages wait for before they switch on
        Schema::dropIfExists('notification_preferences');
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->primary();
            $t->json('settings');
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });
        if (! Schema::hasColumn('user_notifications', 'event_key')) {
            Schema::table('user_notifications', fn (Blueprint $t) => $t->string('event_key', 120)->nullable());
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

    private function staff(User $vendor, array $perms): Employee
    {
        $e = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Cara', 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'status' => 'Active']);
        foreach ($perms as $perm) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $e->id, 'module' => 'crm', 'permission' => $perm, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $e;
    }

    public function test_order_updates_and_products_reach_the_customer_as_cards(): void
    {
        $shop = $this->shopOwner();
        $ana = User::create(['name' => 'Ana', 'surname' => 'Reyes', 'email' => 'ana@example.com', 'password' => 'x', 'role' => 'customer']);
        $orderId = $this->order($shop, ['user_id' => $ana->id, 'order_number' => 'ORD-77', 'status' => 'processing', 'total_amount' => 1850]);
        $rose = DB::table('products')->insertGetId(['owner_id' => $shop->id, 'product_name' => 'Rose box', 'selling_price' => 1500]);
        $chat = DB::table('conversations')->insertGetId(['owner_id' => $shop->id, 'vendor_id' => $shop->id, 'customer_id' => $ana->id]);

        // Someone else's order can't be used
        $other = User::create(['name' => 'Ben', 'surname' => 'Lim', 'email' => 'ben@example.com', 'password' => 'x', 'role' => 'customer']);
        $notHers = $this->order($shop, ['user_id' => $other->id, 'order_number' => 'ORD-78']);

        $writer = $this->staff($shop, ['view', 'create']);
        $this->as($writer)->getJson("/api/chat/conversations/{$chat}/shareables")->assertOk()
            ->assertJsonPath('data.orders.0.order_number', 'ORD-77')
            ->assertJsonPath('data.products.0.name', 'Rose box')
            ->assertJsonCount(1, 'data.orders');
        $this->postJson("/api/chat/conversations/{$chat}/order-update", ['order_id' => $notHers, 'update' => 'on_the_way'])->assertNotFound();
        $this->postJson("/api/chat/conversations/{$chat}/order-update", ['order_id' => $orderId, 'update' => 'note'])->assertStatus(422);

        $this->postJson("/api/chat/conversations/{$chat}/order-update", ['order_id' => $orderId, 'update' => 'on_the_way', 'note' => 'Rider is Jun, ETA 3pm'])
            ->assertOk()->assertJsonPath('data.type', 'order_update')
            ->assertJsonPath('data.meta.order_number', 'ORD-77')
            ->assertJsonPath('data.meta.title', 'On its way');
        $this->postJson("/api/chat/conversations/{$chat}/share-product", ['product_id' => $rose, 'note' => 'This one in red?'])
            ->assertOk()->assertJsonPath('data.meta.name', 'Rose box');

        // The order itself is untouched; the customer is told and sees both cards
        $this->assertSame('processing', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertSame(2, (int) DB::table('conversations')->where('id', $chat)->value('unread_count_customer'));
        $this->assertDatabaseHas('user_notifications', ['user_id' => $ana->id, 'type' => 'order_update', 'title' => 'Your order is on its way']);

        $thread = $this->as($ana)->getJson("/api/chat/conversations/{$chat}/messages")->assertOk()->json('messages');
        $this->assertSame(['order_update', 'product'], array_column($thread, 'type'));
        $this->assertSame('Rider is Jun, ETA 3pm', $thread[0]['meta']['note']);
        $this->assertFalse($thread[0]['is_own']);

        // Customers can't send these, and read-only staff can't either
        $this->postJson("/api/chat/conversations/{$chat}/order-update", ['order_id' => $orderId, 'update' => 'delivered'])->assertNotFound();
        $reader = $this->staff($shop, ['view']);
        $this->as($reader)->postJson("/api/chat/conversations/{$chat}/order-update", ['order_id' => $orderId, 'update' => 'delivered'])->assertForbidden();
    }

    public function test_the_inbox_summary_and_answering_questions_from_the_erp(): void
    {
        $shop = $this->shopOwner();
        $ana = User::create(['name' => 'Ana', 'surname' => 'Reyes', 'email' => 'ana@example.com', 'password' => 'x', 'role' => 'customer']);
        $chat = DB::table('conversations')->insertGetId(['owner_id' => $shop->id, 'vendor_id' => $shop->id, 'customer_id' => $ana->id, 'unread_count_vendor' => 2]);
        $msg = fn ($sender, $at) => DB::table('messages')->insert(['owner_id' => $shop->id, 'conversation_id' => $chat, 'sender_id' => $sender, 'message' => 'hi', 'created_at' => $at, 'updated_at' => $at]);
        $msg($ana->id, '2026-10-19 09:00:00');
        $msg($shop->id, '2026-10-19 09:20:00');   // 20 min
        $msg($ana->id, '2026-10-20 08:00:00');    // waiting since 8am
        $msg($ana->id, '2026-10-20 08:30:00');

        $q = DB::table('customer_inquiries')->insertGetId(['vendor_id' => $shop->id, 'customer_id' => $ana->id, 'question' => 'Do you deliver to Imus?', 'status' => 'open', 'created_at' => '2026-10-18 09:00:00', 'updated_at' => '2026-10-18 09:00:00']);

        $staff = $this->staff($shop, ['view', 'create']);
        $this->as($staff)->getJson('/api/crm/communication')->assertOk()
            ->assertJsonPath('data.unread_messages', 2)
            ->assertJsonPath('data.waiting_chats', 1)
            ->assertJsonPath("data.waiting.{$chat}", Carbon::parse('2026-10-20 08:00:00')->toIso8601String())
            ->assertJsonPath('data.avg_reply_minutes', 20)
            ->assertJsonPath('data.open_inquiries', 1)
            ->assertJsonPath('data.overdue_inquiries', 1);

        $this->getJson('/api/crm/inquiries')->assertOk();
        $this->postJson("/api/crm/inquiries/{$q}/answer", ['answer' => 'Yes, same day before 2pm.'])->assertOk();
        $this->assertDatabaseHas('customer_inquiries', ['id' => $q, 'status' => 'answered', 'answered_by' => 'Cara']);

        // The customer sees the answer in their questions
        $this->as($ana)->getJson('/api/customer/inquiries')->assertOk()->assertSee('Yes, same day before 2pm.');
    }
}
