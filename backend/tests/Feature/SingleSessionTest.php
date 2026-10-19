<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoginAuditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * One sign-in per account: a second device is asked before it takes over,
 * and the replaced device is told why it was signed out.
 */
class SingleSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('surname')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('contact_number')->nullable();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->json('vendor_data')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_suspended')->default(false);
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('api_token', 80)->nullable()->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('session_device', 64)->nullable();
            $table->string('replaced_token_hash', 64)->nullable()->index();
            $table->timestamps();
        });

        // Login looks up staff accounts owned by the user
        Schema::dropIfExists('employees');
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable();
            $table->string('api_token', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Sign-in history isn't what these tests are about
        $this->mock(LoginAuditService::class)->shouldIgnoreMissing();
    }

    private function customer(): User
    {
        return User::forceCreate([
            'name' => 'Ana',
            'username' => 'ana',
            'email' => 'ana@example.com',
            'password' => Hash::make('secret-pass'),
            'role' => 'customer',
        ]);
    }

    private function login(array $extra = [])
    {
        return $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'secret-pass'] + $extra);
    }

    public function test_first_login_signs_straight_in(): void
    {
        $this->customer();

        $this->login(['device_id' => 'laptop'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_asks_when_another_device_is_active(): void
    {
        $this->customer();
        $this->login(['device_id' => 'laptop'])->assertOk();

        $this->login(['device_id' => 'phone'])
            ->assertStatus(409)
            ->assertJson(['success' => false, 'code' => 'session_active']);
    }

    public function test_the_same_browser_is_never_asked(): void
    {
        $this->customer();
        $this->login(['device_id' => 'laptop'])->assertOk();

        $this->login(['device_id' => 'laptop'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_an_idle_session_does_not_count(): void
    {
        $user = $this->customer();
        $this->login(['device_id' => 'laptop'])->assertOk();
        $user->refresh()->forceFill(['last_seen_at' => now()->subMinutes(16)])->save();

        $this->login(['device_id' => 'phone'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_a_logged_out_session_does_not_count(): void
    {
        $this->customer();
        $token = $this->login(['device_id' => 'laptop'])->json('token');
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->flushHeaders();

        $this->login(['device_id' => 'phone'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_continuing_takes_over_and_the_old_device_hears_why(): void
    {
        $this->customer();
        $laptop = $this->login(['device_id' => 'laptop'])->json('token');

        $phone = $this->login(['device_id' => 'phone', 'continue_here' => true])->assertOk()->json('token');
        $this->assertNotSame($laptop, $phone);

        $this->withToken($laptop)->getJson('/api/auth/session')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'code' => 'session_replaced']);

        $this->withToken($phone)->getJson('/api/auth/session')->assertOk()->assertJson(['success' => true]);
    }

    public function test_a_wrong_password_never_reveals_an_active_session(): void
    {
        $this->customer();
        $this->login(['device_id' => 'laptop'])->assertOk();

        $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'nope', 'device_id' => 'phone'])
            ->assertStatus(422)
            ->assertJsonMissing(['code' => 'session_active']);
    }

    public function test_using_the_site_keeps_the_session_active(): void
    {
        $user = $this->customer();
        $token = $this->login(['device_id' => 'laptop'])->json('token');
        $user->refresh()->forceFill(['last_seen_at' => now()->subMinutes(10)])->save();

        $this->withToken($token)->getJson('/api/auth/session')->assertOk();

        $this->assertTrue($user->refresh()->last_seen_at->gt(now()->subMinute()));
    }

    public function test_an_unknown_token_is_still_plainly_unauthenticated(): void
    {
        $this->withToken('not-a-real-token')->getJson('/api/auth/session')
            ->assertStatus(401)
            ->assertJsonMissing(['code' => 'session_replaced']);
    }
}
