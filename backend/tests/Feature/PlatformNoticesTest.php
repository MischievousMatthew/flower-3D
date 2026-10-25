<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Shops and customers hear about admin actions that affect them, and checkout
 * can be paused on a schedule (it starts and ends by itself).
 */
class PlatformNoticesTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        foreach (['surname' => 'string', 'username' => 'string', 'admin_role_id' => 'unsignedBigInteger', 'is_suspended' => 'boolean', 'is_admin_owner' => 'boolean', 'last_seen_at' => 'timestamp'] as $column => $type) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $t) => $type === 'boolean' ? $t->boolean($column)->default(false) : $t->{$type}($column)->nullable());
            }
        }
        foreach (['admin_roles', 'user_notifications', 'site_settings', 'products', 'product_reviews', 'admin_audit_logs'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('admin_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('description')->nullable();
            $t->json('permissions');
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('broadcast_id')->nullable();
            $t->string('type');
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('link_url')->nullable();
            $t->boolean('important')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('site_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->json('value')->nullable();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('product_name')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('product_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedTinyInteger('rating')->default(5);
            $t->text('comment')->nullable();
            $t->timestamp('hidden_at')->nullable();
            $t->string('hidden_reason')->nullable();
            $t->unsignedBigInteger('hidden_by')->nullable();
            $t->timestamps();
        });
        Schema::create('admin_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('admin_id')->nullable();
            $t->string('admin_name')->nullable();
            $t->string('action')->nullable();
            $t->string('method', 10)->nullable();
            $t->string('path')->nullable();
            $t->string('target_id')->nullable();
            $t->string('target_label')->nullable();
            $t->unsignedSmallInteger('status_code')->nullable();
            $t->json('details')->nullable();
            $t->string('ip_address')->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        (new \ReflectionProperty(PlatformSettings::class, 'cache'))->setValue(null, null);

        $role = DB::table('admin_roles')->insertGetId(['name' => 'Super admin', 'permissions' => json_encode(['*']), 'is_system' => true]);
        $this->admin = User::create(['name' => 'Matthew', 'email' => 'admin@example.com', 'password' => Hash::make('x'), 'role' => User::ROLE_ADMIN]);
        $this->admin->forceFill(['admin_role_id' => $role])->save();
    }

    private function shop(string $name, bool $suspended = false): User
    {
        $shop = User::create(['name' => $name, 'email' => strtolower($name) . '@example.com', 'password' => 'x', 'role' => User::ROLE_VENDOR]);
        $shop->forceFill(['is_suspended' => $suspended])->save();

        return $shop;
    }

    /** What the admin page sends: every setting, with these changes */
    private function saveSettings(array $changes)
    {
        $this->app['auth']->forgetGuards();
        $current = $this->signIn($this->admin)->getJson('/api/admin/security/settings')->json('data');

        return $this->putJson('/api/admin/security/settings', array_merge($current, $changes));
    }

    private function noticesFor(User $user)
    {
        return DB::table('user_notifications')->where('user_id', $user->id)->orderBy('id')->get();
    }

    public function test_pausing_checkout_tells_every_active_shop_and_marks_it_important(): void
    {
        $open = $this->shop('Petals');
        $suspended = $this->shop('Closed', true);
        $customer = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x', 'role' => User::ROLE_CUSTOMER]);

        $this->saveSettings(['checkout_paused' => true])->assertOk()->assertJsonPath('notified', 1);

        $notice = $this->noticesFor($open)->sole();
        $this->assertSame('Checkout is paused on BloomCraft', $notice->title);
        $this->assertTrue((bool) $notice->important);
        $this->assertSame('platform', $notice->type);
        $this->assertCount(0, $this->noticesFor($suspended));
        // Customers see the banner instead of a notice each
        $this->assertCount(0, $this->noticesFor($customer));

        $this->saveSettings(['checkout_paused' => false, 'reopen_now' => true])->assertOk();
        $this->assertSame('Checkout is open again', $this->noticesFor($open)->last()->title);
    }

    public function test_a_scheduled_pause_is_announced_then_starts_and_ends_by_itself(): void
    {
        $shop = $this->shop('Petals');
        $start = now()->addHour()->startOfMinute();
        $end = now()->addHours(3)->startOfMinute();

        $this->saveSettings(['checkout_pause_starts_at' => $start->toIso8601String(), 'checkout_pause_ends_at' => $end->toIso8601String()])->assertOk();

        $notice = $this->noticesFor($shop)->sole();
        $this->assertStringStartsWith('Scheduled maintenance: checkout pauses', $notice->title);
        $this->assertTrue((bool) $notice->important);
        $this->assertFalse(PlatformSettings::checkoutPausedNow());
        $this->getJson('/api/site/status')->assertJsonPath('data.checkout_paused', false)->assertJsonPath('data.checkout_pause_starts_at', $start->toIso8601String());

        $this->travelTo($start->addMinute());
        $this->assertTrue(PlatformSettings::checkoutPausedNow());
        $this->getJson('/api/site/status')->assertJsonPath('data.checkout_paused', true);

        $this->travelTo($end->addMinute());
        $this->assertFalse(PlatformSettings::checkoutPausedNow());
        $this->getJson('/api/site/status')->assertJsonPath('data.checkout_paused', false)->assertJsonPath('data.checkout_pause_starts_at', null);
    }

    public function test_the_switch_still_works_at_once_during_a_scheduled_pause(): void
    {
        $shop = $this->shop('Petals');
        $this->saveSettings(['checkout_pause_starts_at' => now()->addMinutes(10)->toIso8601String(), 'checkout_pause_ends_at' => now()->addHours(2)->toIso8601String()])->assertOk();
        $this->travel(11)->minutes();
        $this->assertTrue(PlatformSettings::checkoutPausedNow());

        $this->saveSettings(['checkout_paused' => false, 'reopen_now' => true])->assertOk()->assertJsonPath('data.checkout_paused_now', false);

        $this->assertFalse(PlatformSettings::checkoutPausedNow());
        $this->assertNull(PlatformSettings::get('checkout_pause_starts_at'));
        $this->assertSame('Checkout is open again', $this->noticesFor($shop)->last()->title);
    }

    public function test_cancelling_a_planned_pause_tells_the_shops_and_bad_times_are_refused(): void
    {
        $shop = $this->shop('Petals');
        $this->saveSettings(['checkout_pause_starts_at' => now()->subHour()->toIso8601String()])->assertStatus(422);
        $this->saveSettings(['checkout_pause_starts_at' => now()->addHours(2)->toIso8601String(), 'checkout_pause_ends_at' => now()->addHour()->toIso8601String()])->assertStatus(422);

        $this->saveSettings(['checkout_pause_starts_at' => now()->addDay()->toIso8601String()])->assertOk();
        $this->saveSettings(['checkout_pause_starts_at' => null, 'checkout_pause_ends_at' => null])->assertOk();

        $this->assertSame('Scheduled maintenance cancelled', $this->noticesFor($shop)->last()->title);
    }

    public function test_hiding_a_review_tells_the_customer_who_wrote_it_and_why(): void
    {
        $shop = $this->shop('Petals');
        $customer = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x', 'role' => User::ROLE_CUSTOMER]);
        $product = DB::table('products')->insertGetId(['owner_id' => $shop->id, 'product_name' => 'Rose box']);
        $review = DB::table('product_reviews')->insertGetId(['product_id' => $product, 'user_id' => $customer->id, 'rating' => 1, 'comment' => 'rude words']);

        $this->signIn($this->admin)->postJson("/api/admin/reviews/{$review}/hide", ['reason' => 'It had rude language.'])->assertOk();

        $notice = $this->noticesFor($customer)->sole();
        $this->assertSame('BloomCraft hid your review of Rose box', $notice->title);
        $this->assertStringContainsString('It had rude language.', $notice->body);
        $this->assertTrue((bool) $notice->important);
    }

    public function test_important_unread_notices_are_listed_first(): void
    {
        $customer = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x', 'role' => User::ROLE_CUSTOMER]);
        $row = fn ($title, $important, $minutesAgo, $read = false) => DB::table('user_notifications')->insert([
            'user_id' => $customer->id, 'type' => 'platform', 'title' => $title, 'important' => $important,
            'read_at' => $read ? now() : null, 'created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now(),
        ]);
        $row('Old but important', true, 60);
        $row('Newest', false, 1);
        $row('Important but already read', true, 30, true);

        $titles = $this->signIn($customer)->getJson('/api/notifications')->assertOk()->json('data.*.title');

        $this->assertSame(['Old but important', 'Newest', 'Important but already read'], $titles);
    }
}
