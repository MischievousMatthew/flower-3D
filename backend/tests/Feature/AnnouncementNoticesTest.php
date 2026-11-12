<?php

namespace Tests\Feature;

use App\Http\Controllers\SiteContentController;
use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementNotices;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Announcements from Admin → Promotion Management reach customers'
 * notifications once each, follow edits, leave when switched off or
 * deleted, and the announcement bar is only sent to signed-in customers.
 */
class AnnouncementNoticesTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_suspended')->default(false);
            $t->timestamp('last_seen_at')->nullable();
        });
        foreach (['user_notifications', 'announcements'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20)->default('announcement');
            $t->string('title', 120);
            $t->string('message', 300)->nullable();
            $t->string('promo_code', 40)->nullable();
            $t->text('image_url')->nullable();
            $t->string('image_path')->nullable();
            $t->string('link_label', 40)->nullable();
            $t->string('link_type', 20)->nullable();
            $t->string('link_value')->nullable();
            $t->string('tone', 20)->default('brand');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('broadcast_id')->nullable();
            $t->string('type', 30);
            $t->string('event_key', 120)->nullable();
            $t->string('title', 160);
            $t->text('body')->nullable();
            $t->string('link_url', 500)->nullable();
            $t->boolean('important')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'event_key']);
        });
    }

    private function user(string $role, string $email): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $email, 'password' => Hash::make('secret'), 'role' => $role]);
    }

    private function announcement(array $attrs = []): Announcement
    {
        return Announcement::create($attrs + ['kind' => 'announcement', 'title' => 'Free delivery this weekend', 'message' => 'On orders over ₱1,500.', 'is_active' => true]);
    }

    public function test_customer_gets_each_showing_announcement_once(): void
    {
        $customer = $this->user('customer', 'c@example.com');
        $live = $this->announcement(['link_type' => 'category', 'link_value' => 'roses']);
        $this->announcement(['title' => 'Later', 'starts_at' => now()->addDay()]);
        $this->announcement(['title' => 'Off', 'is_active' => false]);
        $this->announcement(['title' => 'A promo', 'kind' => 'promotion']);

        $this->signIn($customer)->getJson('/api/notifications/unread-count')->assertOk()->assertJson(['unread' => 1]);
        $this->getJson('/api/notifications')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Free delivery this weekend')
            ->assertJsonPath('data.0.type', 'announcement')
            ->assertJsonPath('data.0.link_url', 'route:Shop?category=roses');

        // Checking again doesn't add it twice
        $this->getJson('/api/notifications/unread-count')->assertJson(['unread' => 1]);
        $this->assertSame(1, DB::table('user_notifications')->where('event_key', 'site_announcement:' . $live->id)->count());
    }

    public function test_vendors_dont_get_shop_announcements(): void
    {
        $this->announcement();
        app(AnnouncementNotices::class)->deliver($this->user('vendor', 'v@example.com'));

        $this->assertSame(0, DB::table('user_notifications')->count());
    }

    public function test_edits_follow_and_switching_off_or_deleting_takes_it_back(): void
    {
        $a = $this->user('customer', 'a@example.com');
        $b = $this->user('customer', 'b@example.com');
        $item = $this->announcement();
        $notices = app(AnnouncementNotices::class);
        $notices->deliver($a);
        $notices->deliver($b);
        DB::table('user_notifications')->where('user_id', $a->id)->update(['read_at' => now()]);

        $item->update(['title' => 'Free delivery all week']);
        $notices->refresh($item);
        $this->assertSame(2, DB::table('user_notifications')->where('title', 'Free delivery all week')->count());

        // Switched off: only the unread one goes
        $item->update(['is_active' => false]);
        $notices->refresh($item);
        $this->assertSame([$a->id], DB::table('user_notifications')->pluck('user_id')->all());

        $notices->refresh($item, deleted: true);
        $this->assertSame(0, DB::table('user_notifications')->count());
    }

    public function test_announcement_bar_is_only_for_signed_in_customers(): void
    {
        $check = function (?string $token): bool {
            $request = Request::create('/api/site/home');
            if ($token) {
                $request->headers->set('Authorization', "Bearer {$token}");
            }
            $method = new \ReflectionMethod(SiteContentController::class, 'viewerIsCustomer');

            return $method->invoke(new SiteContentController(), $request);
        };
        $token = function (User $user): string {
            $plain = bin2hex(random_bytes(20));
            $user->forceFill(['api_token' => hash('sha256', $plain)])->save();

            return $plain;
        };

        $this->assertFalse($check(null), 'guest');
        $this->assertFalse($check('not-a-real-token'), 'bad token');
        $this->assertFalse($check($token($this->user('vendor', 'v@example.com'))), 'vendor');
        $this->assertTrue($check($token($this->user('customer', 'c@example.com'))), 'customer');
    }
}
