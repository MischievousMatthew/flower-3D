<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoginAuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesAuthTables;
use Tests\TestCase;

/** Protections around signing in, from the authentication audit */
class AuthSecurityTest extends TestCase
{
    use CreatesAuthTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthTables();
        $this->mock(LoginAuditService::class)->shouldIgnoreMissing();
        Cache::flush();
    }

    private function vendorWithTwoFactor(): User
    {
        return User::forceCreate([
            'name' => 'Camille', 'username' => 'camille', 'email' => 'camille@example.com',
            'password' => Hash::make('Secret-pass1'), 'role' => 'vendor', 'two_factor_enabled' => true,
        ]);
    }

    public function test_there_is_no_sign_in_that_skips_two_factor(): void
    {
        $this->vendorWithTwoFactor();

        // The old vendor-only sign-in handed out a token without the second step
        $this->postJson('/api/auth/vendor/login', ['username' => 'camille', 'password' => 'Secret-pass1'])->assertNotFound();

        $this->postJson('/api/auth/login', ['username' => 'camille', 'password' => 'Secret-pass1'])
            ->assertOk()->assertJson(['requires_two_factor' => true])->assertJsonMissing(['token']);
        $this->assertNull(User::first()->api_token);
    }

    private function signedInCustomer(): string
    {
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer']);

        return $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'Secret-pass1'])->assertOk()->json('token');
    }

    public function test_an_unused_token_stops_working_on_the_server(): void
    {
        $token = $this->signedInCustomer();
        $this->withToken($token)->getJson('/api/auth/session')->assertOk();

        $this->travel(31)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/session')
            ->assertStatus(401)->assertJson(['code' => 'session_expired']);
        // Ended for good: the same token can't come back
        $this->assertNull(User::first()->api_token);
    }

    public function test_checking_usernames_does_not_use_up_the_sign_up_code_limit(): void
    {
        // The sign-up form checks the username as it's typed
        foreach (range(1, 12) as $i) {
            $this->postJson('/api/auth/check-availability', ['username' => "ana{$i}"])->assertOk();
        }
        // No email service in tests, so the code can't be sent, but it isn't "too many tries"
        $status = $this->postJson('/api/auth/send-otp', ['email' => 'ana@example.com'])->status();
        $this->assertNotSame(429, $status);
    }

    public function test_one_address_cannot_keep_guessing_passwords(): void
    {
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer']);

        // 20 tries a minute, each one staff sign-in then customer sign-in, spread over many accounts
        foreach (range(1, 20) as $i) {
            $this->postJson('/api/auth/employee-login', ['username' => "someone{$i}", 'password' => 'guess'])->assertStatus(401);
            $this->postJson('/api/auth/login', ['username' => "someone{$i}", 'password' => 'guess'])->assertStatus(422);
        }
        $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'Secret-pass1'])
            ->assertStatus(429)->assertJson(['code' => 'too_many_requests', 'message' => 'Too many tries. Please wait a moment and try again.']);

        // A minute later it works again
        $this->travel(61)->seconds();
        $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'Secret-pass1'])->assertOk();
    }

    public function test_signing_in_writes_no_personal_details_to_the_log(): void
    {
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'contact_number' => '09171234567', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer']);
        $written = [];
        \Illuminate\Support\Facades\Log::listen(function ($entry) use (&$written) {
            $written[] = $entry->message . ' ' . json_encode($entry->context);
        });

        // A password typed into the username box, then a real sign-in
        $this->postJson('/api/auth/login', ['username' => 'Secret-pass1', 'password' => 'x'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'Secret-pass1'])->assertOk();

        $log = implode("\n", $written);
        foreach (['Secret-pass1', 'ana@example.com', '09171234567', 'select '] as $private) {
            $this->assertStringNotContainsString($private, $log);
        }
    }

    public function test_capitals_in_the_username_or_email_do_not_matter_when_signing_in(): void
    {
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer']);
        $this->postJson('/api/auth/login', ['username' => ' Ana@Example.com ', 'password' => 'Secret-pass1'])->assertOk();

        // What a successful staff sign-in reads next
        \Illuminate\Support\Facades\Schema::create('employee_module_permissions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('module');
            $t->string('permission');
            $t->timestamps();
        });
        \Illuminate\Support\Facades\Schema::dropIfExists('vendor_applications');
        \Illuminate\Support\Facades\Schema::create('vendor_applications', function ($t) {
            $t->id();
            $t->string('email')->nullable();
            $t->string('store_name')->nullable();
            $t->string('registered_business_name')->nullable();
            $t->string('store_logo_path')->nullable();
        });
        \Illuminate\Support\Facades\DB::table('employees')->insert(['owner_id' => 1, 'name' => 'Joy', 'username' => 'joy.staff', 'email' => 'joy@example.com', 'password' => Hash::make('Staff-pass1'), 'status' => 'Active']);
        $this->postJson('/api/auth/employee-login', ['username' => 'JOY.STAFF', 'password' => 'Staff-pass1'])->assertOk()->assertJson(['success' => true]);
    }

    public function test_older_accounts_that_differ_only_by_capitals_each_still_sign_in(): void
    {
        User::forceCreate(['name' => 'Ana', 'username' => 'Ana', 'email' => 'ana1@example.com', 'password' => Hash::make('First-pass1'), 'role' => 'customer']);
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana2@example.com', 'password' => Hash::make('Second-pass2'), 'role' => 'customer']);

        $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'First-pass1'])->assertOk()->assertJsonPath('user.email', 'ana1@example.com');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'Second-pass2', 'continue_here' => true])->assertOk()->assertJsonPath('user.email', 'ana2@example.com');
    }

    public function test_sign_up_refuses_a_username_or_email_that_only_differs_by_capitals(): void
    {
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer']);

        $this->postJson('/api/auth/register', [
            'name' => 'Ana', 'surname' => 'Cruz', 'username' => 'ANA', 'email' => 'Ana@Example.com',
            'password' => 'Secret-pass1', 'password_confirmation' => 'Secret-pass1', 'otp' => '123456',
        ])->assertStatus(422)->assertJsonValidationErrors(['username', 'email']);
        $this->assertSame(1, User::count());
    }

    public function test_an_email_code_only_works_for_what_it_was_sent_for(): void
    {
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer']);
        $code = fn (string $email, string $purpose) => \App\Models\EmailOtp::create([
            'email' => $email, 'otp_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(3), 'attempts' => 0, 'is_locked' => false, 'purpose' => $purpose,
        ]);

        // A password-reset code can't be used to open a new account
        $code('new@example.com', 'reset');
        $this->postJson('/api/auth/register', [
            'name' => 'New', 'surname' => 'Person', 'username' => 'newperson', 'email' => 'new@example.com',
            'password' => 'Secret-pass1', 'password_confirmation' => 'Secret-pass1', 'otp' => '123456',
        ])->assertStatus(422)->assertJsonPath('errors.otp.0', 'That code was sent for something else. Ask for a new one here.');
        $this->assertSame(1, User::count());

        // …but it does reset the password it was sent for
        $code('ana@example.com', 'reset');
        $this->postJson('/api/auth/password/reset', ['identifier' => 'ana', 'otp' => '123456', 'password' => 'Newer-pass2', 'password_confirmation' => 'Newer-pass2'])->assertOk();
    }

    public function test_shop_owners_changing_their_password_follow_the_same_rule_as_sign_up(): void
    {
        $owner = User::forceCreate(['name' => 'Camille', 'username' => 'camille', 'email' => 'camille@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'vendor']);
        $token = $this->postJson('/api/auth/login', ['username' => 'camille', 'password' => 'Secret-pass1'])->assertOk()->json('token');

        $this->withToken($token)->putJson('/api/vendor/profile/change-password', [
            'current_password' => 'Secret-pass1', 'password' => 'alllowercase', 'password_confirmation' => 'alllowercase',
        ])->assertStatus(422)->assertJsonPath('errors.password.0', 'Use at least one uppercase letter, one lowercase letter and a number.');
        $this->assertTrue(Hash::check('Secret-pass1', $owner->fresh()->password));
    }

    public function test_an_application_status_needs_its_private_link_not_just_an_email(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('vendor_applications');
        \Illuminate\Support\Facades\Schema::create('vendor_applications', function ($t) {
            $t->id();
            $t->string('application_id')->nullable();
            $t->string('status_token')->nullable();
            $t->string('email')->nullable();
            $t->string('store_name')->nullable();
            $t->string('status')->default('pending');
            $t->string('verification_level')->nullable();
            $t->boolean('payment_details_completed')->default(false);
            $t->boolean('product_details_completed')->default(false);
            $t->boolean('delivery_details_completed')->default(false);
            $t->boolean('profile_fully_completed')->default(false);
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
        });
        \Illuminate\Support\Facades\DB::table('vendor_applications')->insert([
            'application_id' => 'VA-2026-0001', 'status_token' => 'private-token-123', 'email' => 'shop@example.com',
            'store_name' => 'Petal & Stem', 'submitted_at' => now(),
        ]);

        // The old lookup by email alone is gone
        $this->getJson('/api/vendor/status?email=shop@example.com')->assertNotFound();
        $this->getJson('/api/vendor/status/VA-2026-0001?token=guess')->assertNotFound();
        $this->getJson('/api/vendor/status/VA-2026-0001?token=private-token-123')->assertOk()->assertJsonPath('data.store_name', 'Petal & Stem');
    }

    public function test_a_half_filled_staff_login_is_told_what_is_missing(): void
    {
        $this->postJson('/api/auth/employee-login', ['username' => 'joy'])
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_a_token_kept_in_plain_text_is_not_accepted(): void
    {
        $plain = str_repeat('a', 80);
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer', 'api_token' => $plain]);

        $this->withToken($plain)->getJson('/api/auth/session')->assertStatus(401);
    }

    public function test_an_unknown_username_still_costs_one_password_check(): void
    {
        // So a wrong name answers as slowly as a wrong password and can't be told apart
        Hash::partialMock()->shouldReceive('check')->once()->andReturnFalse();

        [$account, $ok] = \App\Support\AccountLookup::signIn(User::query(), 'nobody-here', 'guess');

        $this->assertNull($account);
        $this->assertFalse($ok);
    }

    public function test_the_admin_sets_how_many_sign_in_tries_one_connection_gets(): void
    {
        \App\Support\PlatformSettings::save(['login_ip_per_minute' => 10]);

        foreach (range(1, 10) as $i) {
            $this->postJson('/api/auth/employee-login', ['username' => "someone{$i}", 'password' => 'guess'])->assertStatus(401);
            $this->postJson('/api/auth/login', ['username' => "someone{$i}", 'password' => 'guess'])->assertStatus(422);
        }
        $this->postJson('/api/auth/login', ['username' => 'someone', 'password' => 'guess'])->assertStatus(429);
    }

    public function test_a_session_in_use_never_expires(): void
    {
        $token = $this->signedInCustomer();
        // An open tab checks in every minute, for well over the idle limit
        foreach (range(1, 8) as $i) {
            $this->travel(10)->minutes();
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson('/api/auth/session')->assertOk();
        }
    }
}
