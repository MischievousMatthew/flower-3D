<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * "Ask the shop" from a product page or an order: the customer's question
 * reaches the shop's chat with a card for that product or order.
 */
class ChatAboutProductOrOrderTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    private User $shop;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        foreach (['surname' => 'string', 'is_suspended' => 'boolean', 'last_seen_at' => 'timestamp'] as $column => $type) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $t) => $type === 'boolean' ? $t->boolean($column)->default(false) : $t->{$type}($column)->nullable());
            }
        }
        foreach (['conversations', 'messages', 'products', 'product_images', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->string('last_message')->nullable();
            $t->timestamp('last_message_time')->nullable();
            $t->unsignedBigInteger('last_message_sender_id')->nullable();
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
            $t->json('meta')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('product_name');
            $t->decimal('selling_price', 10, 2)->nullable();
            $t->boolean('has_discount')->default(false);
            $t->decimal('discount_price', 10, 2)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('image_url');
            $t->boolean('is_primary')->default(false);
            $t->unsignedInteger('display_order')->default(0);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('vendor_id');
            $t->string('status');
            $t->decimal('total_amount', 10, 2)->default(0);
            $t->timestamps();
        });

        Schema::dropIfExists('customer_inquiries');
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
        (require database_path('migrations/2026_11_03_700000_link_customer_inquiries_to_chat.php'))->up();

        $this->shop = User::create(['name' => 'Flux', 'email' => 'flux@example.com', 'password' => 'x', 'role' => User::ROLE_VENDOR]);
        $this->customer = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x', 'role' => User::ROLE_CUSTOMER]);
    }

    /** Opens the chat the way "Ask the shop" does: no greeting first */
    private function openChat(): int
    {
        $id = $this->signIn($this->customer)->postJson('/api/chat/conversations/start', ['vendor_id' => $this->shop->id])
            ->assertOk()->json('conversation.id');
        $this->assertSame(0, DB::table('messages')->count());

        return $id;
    }

    public function test_a_question_about_a_product_carries_the_product_card(): void
    {
        $productId = DB::table('products')->insertGetId(['owner_id' => $this->shop->id, 'product_name' => 'Red Roses', 'selling_price' => 1200]);
        DB::table('product_images')->insert(['product_id' => $productId, 'image_url' => 'https://img.test/roses.jpg', 'is_primary' => true]);
        $chat = $this->openChat();

        $this->signIn($this->customer)->postJson('/api/chat/messages/send', [
            'conversation_id' => $chat, 'message' => 'Do you have these in pink?', 'about_product_id' => $productId,
        ])->assertOk()
            ->assertJsonPath('message.type', 'product_question')
            ->assertJsonPath('message.text', 'Do you have these in pink?')
            ->assertJsonPath('message.meta.name', 'Red Roses')
            ->assertJsonPath('message.meta.image', 'https://img.test/roses.jpg');

        $this->assertSame('Red Roses: Do you have these in pink?', DB::table('conversations')->value('last_message'));
        // Saved with the card, so the shop's chat shows it too, and it's unread for the shop
        $saved = DB::table('messages')->sole();
        $this->assertSame('product_question', $saved->message_type);
        $this->assertSame('Red Roses', json_decode($saved->meta, true)['name']);
        $this->assertSame(1, (int) DB::table('conversations')->value('unread_count_vendor'));
    }

    public function test_a_question_about_an_order_carries_the_order_card(): void
    {
        $orderId = DB::table('orders')->insertGetId(['order_number' => 'ORD-0042', 'user_id' => $this->customer->id, 'vendor_id' => $this->shop->id, 'status' => 'processing', 'total_amount' => 1850]);
        $chat = $this->openChat();

        $this->signIn($this->customer)->postJson('/api/chat/messages/send', [
            'conversation_id' => $chat, 'message' => 'Can you deliver it before 5 PM?', 'about_order_id' => $orderId,
        ])->assertOk()
            ->assertJsonPath('message.type', 'order_question')
            ->assertJsonPath('message.meta.order_number', 'ORD-0042')
            ->assertJsonPath('message.meta.order_status', 'processing');
    }

    public function test_someone_elses_order_or_another_shops_product_is_refused(): void
    {
        $other = User::create(['name' => 'Ben', 'email' => 'ben@example.com', 'password' => 'x', 'role' => User::ROLE_CUSTOMER]);
        $otherShop = User::create(['name' => 'Petal', 'email' => 'petal@example.com', 'password' => 'x', 'role' => User::ROLE_VENDOR]);
        $notTheirOrder = DB::table('orders')->insertGetId(['order_number' => 'ORD-0099', 'user_id' => $other->id, 'vendor_id' => $this->shop->id, 'status' => 'pending']);
        $otherProduct = DB::table('products')->insertGetId(['owner_id' => $otherShop->id, 'product_name' => 'Tulips', 'selling_price' => 900]);
        $chat = $this->openChat();

        $this->signIn($this->customer)->postJson('/api/chat/messages/send', ['conversation_id' => $chat, 'message' => 'Where is it?', 'about_order_id' => $notTheirOrder])
            ->assertStatus(422);
        $this->signIn($this->customer)->postJson('/api/chat/messages/send', ['conversation_id' => $chat, 'message' => 'In stock?', 'about_product_id' => $otherProduct])
            ->assertStatus(422);
        $this->assertSame(0, DB::table('messages')->count());
    }

    public function test_the_card_needs_a_question_with_it(): void
    {
        $productId = DB::table('products')->insertGetId(['owner_id' => $this->shop->id, 'product_name' => 'Red Roses', 'selling_price' => 1200]);
        $chat = $this->openChat();

        $this->signIn($this->customer)->postJson('/api/chat/messages/send', ['conversation_id' => $chat, 'about_product_id' => $productId])
            ->assertStatus(422)->assertJsonValidationErrors(['message']);
    }
    public function test_a_chat_question_is_also_on_the_questions_page_and_a_chat_reply_answers_it(): void
    {
        $productId = DB::table('products')->insertGetId(['owner_id' => $this->shop->id, 'product_name' => 'Red Roses', 'selling_price' => 1200]);
        $chat = $this->openChat();

        $this->signIn($this->customer)->postJson('/api/chat/messages/send', [
            'conversation_id' => $chat, 'message' => 'Do you have these in pink?', 'about_product_id' => $productId,
        ])->assertOk();

        $q = DB::table('customer_inquiries')->sole();
        $this->assertSame('Do you have these in pink?', $q->question);
        $this->assertSame($productId, (int) $q->product_id);
        $this->assertSame($chat, (int) $q->conversation_id);
        $this->assertSame('open', $q->status);
        $this->assertFalse((bool) $q->is_public);

        // The shop answers in the chat (its plan includes chat): the question is answered
        $this->mock(\App\Services\SubscriptionAccessService::class)->shouldReceive('canAccess')->andReturn(true);
        $this->app['auth']->forgetGuards();
        $this->signIn($this->shop)->postJson('/api/chat/messages/send', ['conversation_id' => $chat, 'message' => 'Yes! Pink ones arrive Friday.'])->assertOk();

        $q = DB::table('customer_inquiries')->sole();
        $this->assertSame('answered', $q->status);
        $this->assertSame('Yes! Pink ones arrive Friday.', $q->answer);
    }

    public function test_an_answer_on_the_questions_page_is_posted_in_the_chat(): void
    {
        $productId = DB::table('products')->insertGetId(['owner_id' => $this->shop->id, 'product_name' => 'Red Roses', 'selling_price' => 1200]);
        $chat = $this->openChat();
        $this->signIn($this->customer)->postJson('/api/chat/messages/send', [
            'conversation_id' => $chat, 'message' => 'Is there a card?', 'about_product_id' => $productId,
        ])->assertOk();

        $inquiry = \App\Models\CustomerInquiry::sole();
        $this->assertTrue(\App\Services\ChatQuestions::postAnswer($inquiry, 'Yes, a free card comes with it.'));

        $reply = DB::table('messages')->orderByDesc('id')->first();
        $this->assertSame($this->shop->id, (int) $reply->sender_id);
        $this->assertSame('Yes, a free card comes with it.', $reply->message);
        $this->assertSame(1, (int) DB::table('conversations')->value('unread_count_customer'));
    }

    public function test_an_ordinary_chat_message_is_not_a_question(): void
    {
        $chat = $this->openChat();
        $this->signIn($this->customer)->postJson('/api/chat/messages/send', ['conversation_id' => $chat, 'message' => 'Hello!'])->assertOk();

        $this->assertSame(0, DB::table('customer_inquiries')->count());
    }
}
