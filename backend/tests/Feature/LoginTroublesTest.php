<?php

namespace Tests\Feature;

use App\Models\EmailOtp;
use App\Models\User;
use App\Services\LoginAuditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sign-in problems are explained (wrong password with tries left, lock with
 * time left, staff account off), counted once, and a forgotten password can
 * be reset by email.
 */
class LoginTroublesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['login_attempts', 'login_audit_logs', 'email_otps', 'employees', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
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
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_suspended')->default(false);
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('api_token', 80)->nullable()->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('session_device', 64)->nullable();
            $table->string('replaced_token_hash', 64)->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('Active');
            $table->string('api_token', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 190)->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_type', 20)->default('user');
            $table->string('reason', 40);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('login_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('logged_in_at')->nullable();
        });
        Schema::create('email_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
        $this->mock(LoginAuditService::class)->shouldIgnoreMissing();
        Cache::flush();
        config(['services.brevo.key' => 'test-key', 'mail.from.address' => 'hello@bloomcraft.test', 'mail.from.name' => 'BloomCraft']);
    }

    private function customer(): User
    {
        return User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer']);
    }

    private function login(string $password, string $username = 'ana')
    {
        return $this->postJson('/api/auth/login', ['username' => $username, 'password' => $password]);
    }

    /** The code in the last email sent through the fake mail API */
    private function emailedCode(): string
    {
        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            preg_match('/Your code: (\d{6})/', $request['textContent'] ?? '', $m);
            $code = $m[1] ?? $code;

            return true;
        });

        return $code;
    }

    public function test_a_wrong_password_says_how_many_tries_are_left_and_then_how_long_to_wait(): void
    {
        $this->customer();
        $this->login('nope')->assertStatus(422)->assertJson(['code' => 'invalid_credentials', 'attempts_left' => 4]);
        foreach ([3, 2, 1] as $left) {
            $this->login('nope')->assertJson(['attempts_left' => $left]);
        }
        $locked = $this->login('nope')->assertStatus(422)->assertJson(['code' => 'account_locked'])->json();
        $this->assertGreaterThan(800, $locked['retry_after']);
        // Even the right password waits until the lock ends
        $this->login('Secret-pass1')->assertJson(['code' => 'account_locked']);
    }

    public function test_one_wrong_password_counts_once_even_though_staff_sign_in_is_tried_first(): void
    {
        $this->customer();
        // What the login form does: staff sign-in, then customer sign-in
        $this->postJson('/api/auth/employee-login', ['username' => 'ana', 'password' => 'nope'])->assertStatus(401);
        $this->login('nope')->assertJson(['attempts_left' => 4]);
        $this->assertSame(1, DB::table('login_attempts')->where('reason', 'wrong_password')->count());
    }

    public function test_names_without_an_account_never_lock(): void
    {
        foreach (range(1, 7) as $i) {
            $this->login('nope', 'ghost')->assertJson(['code' => 'invalid_credentials']);
        }
    }

    public function test_an_inactive_staff_account_is_told_so_only_after_the_right_password(): void
    {
        DB::table('employees')->insert(['name' => 'Ben', 'username' => 'ben', 'email' => 'ben@shop.test', 'password' => Hash::make('Staff-pass1'), 'status' => 'Inactive']);

        $this->postJson('/api/auth/employee-login', ['username' => 'ben', 'password' => 'wrong'])->assertStatus(401)->assertJsonMissing(['code' => 'account_inactive']);
        $this->postJson('/api/auth/employee-login', ['username' => 'ben', 'password' => 'Staff-pass1'])
            ->assertStatus(403)->assertJson(['code' => 'account_inactive'])->assertJsonMissingPath('token');
    }

    public function test_signing_up_needs_a_code_that_was_actually_sent(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Mia', 'surname' => 'Cruz', 'username' => 'mia', 'email' => 'mia@example.com',
            'password' => 'Secret-pass1', 'password_confirmation' => 'Secret-pass1', 'otp' => '123456',
        ])->assertStatus(422)->assertJsonValidationErrors('otp');
        $this->assertDatabaseMissing('users', ['email' => 'mia@example.com']);
    }

    public function test_sign_up_code_requests_wait_politely_and_registered_emails_are_pointed_to_login(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '1'], 201)]);
        $this->customer();

        $this->postJson('/api/auth/send-otp', ['email' => 'ana@example.com'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/auth/send-otp', ['email' => 'new@example.com'])->assertOk()->assertJson(['retry_after' => 60, 'expires_in' => 180]);
        $this->postJson('/api/auth/send-otp', ['email' => 'new@example.com'])->assertStatus(429)->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_email_trouble_is_not_shown_as_a_server_error_dump(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response('{"code":"unauthorized","message":"Key not found"}', 401)]);
        $res = $this->postJson('/api/auth/send-otp', ['email' => 'new@example.com'])->assertStatus(503);
        $this->assertStringNotContainsString('Key not found', $res->getContent());
    }

    public function test_availability_check(): void
    {
        $this->customer();
        $this->postJson('/api/auth/check-availability', ['username' => 'ANA', 'email' => 'fresh@example.com'])
            ->assertOk()->assertJson(['available' => ['username' => false, 'email' => true]]);
    }

    public function test_a_forgotten_password_can_be_reset_by_email_and_clears_the_lock(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '1'], 201)]);
        $user = $this->customer();
        foreach (range(1, 5) as $i) {
            $this->login('nope');
        }
        $this->login('Secret-pass1')->assertJson(['code' => 'account_locked']);

        $this->postJson('/api/auth/password/forgot', ['identifier' => 'ana'])->assertOk();
        $code = $this->emailedCode();

        $this->postJson('/api/auth/password/reset', ['identifier' => 'ana', 'otp' => $code === '000000' ? '111111' : '000000', 'password' => 'Brand-new1', 'password_confirmation' => 'Brand-new1'])
            ->assertStatus(422)->assertJsonValidationErrors('otp');
        $this->postJson('/api/auth/password/reset', ['identifier' => 'ana', 'otp' => $code, 'password' => 'Brand-new1', 'password_confirmation' => 'Brand-new1'])
            ->assertOk()->assertJson(['username' => 'ana']);

        $this->assertTrue(Hash::check('Brand-new1', $user->fresh()->password));
        $this->login('Brand-new1')->assertOk()->assertJsonStructure(['token']);
    }

    public function test_a_code_stops_working_after_three_minutes(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '1'], 201)]);
        $this->customer();
        $this->postJson('/api/auth/password/forgot', ['identifier' => 'ana'])->assertOk()->assertJson(['expires_in' => 180]);
        $code = $this->emailedCode();

        $this->travel(181)->seconds();
        $this->postJson('/api/auth/password/reset', ['identifier' => 'ana', 'otp' => $code, 'password' => 'Brand-new1', 'password_confirmation' => 'Brand-new1'])
            ->assertStatus(422)->assertJsonPath('errors.otp.0', 'That code has expired. Ask for a new one.');
    }

    public function test_forgot_password_answers_the_same_for_unknown_accounts(): void
    {
        Http::fake();
        $this->postJson('/api/auth/password/forgot', ['identifier' => 'nobody'])->assertOk()->assertJson(['success' => true]);
        Http::assertNothingSent();
        $this->postJson('/api/auth/password/reset', ['identifier' => 'nobody', 'otp' => '123456', 'password' => 'Brand-new1', 'password_confirmation' => 'Brand-new1'])
            ->assertStatus(422)->assertJsonValidationErrors('otp');
    }
}
