<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountSuspensionService;
use App\Services\LoginAuditService;
use App\Support\EndedSessions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAuthTables;
use Tests\TestCase;

/**
 * Every kind of account (ERP staff included) is told why it was signed out,
 * instead of a bare "Unauthenticated" that sends the browser back to login.
 */
class EndedSessionsTest extends TestCase
{
    use CreatesAuthTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthTables();
        $this->mock(LoginAuditService::class)->shouldIgnoreMissing();
        Cache::flush();

        Schema::dropIfExists('ended_sessions');
        Schema::create('ended_sessions', function (Blueprint $t) {
            $t->string('token_hash', 64)->primary();
            $t->string('code', 40);
            $t->string('message', 300);
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::dropIfExists('employee_module_permissions');
        Schema::create('employee_module_permissions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('module');
            $t->string('permission');
            $t->timestamps();
        });
        Schema::dropIfExists('vendor_applications');
        Schema::create('vendor_applications', function (Blueprint $t) {
            $t->id();
            $t->string('email')->nullable();
            $t->string('store_name')->nullable();
            $t->string('registered_business_name')->nullable();
            $t->string('store_logo_path')->nullable();
        });
        Schema::dropIfExists('account_events');
        Schema::create('account_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('admin_id')->nullable();
            $t->string('action');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
    }

    private function staff(): void
    {
        User::forceCreate(['id' => 1, 'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com', 'password' => Hash::make('Owner-pass1'), 'role' => 'vendor']);
        DB::table('employees')->insert(['owner_id' => 1, 'name' => 'Joy', 'username' => 'joy.staff', 'email' => 'joy@example.com', 'password' => Hash::make('Staff-pass1'), 'status' => 'Active']);
    }

    private function staffLogin(array $extra = ['device_id' => 'office-pc']): string
    {
        return $this->postJson('/api/auth/employee-login', ['username' => 'joy.staff', 'password' => 'Staff-pass1'] + $extra)->assertOk()->json('token');
    }

    private function asToken(string $token, string $url)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson($url);
    }

    public function test_staff_signed_in_on_another_device_are_told_why(): void
    {
        $this->staff();
        $first = $this->staffLogin();

        // A second browser is asked first
        $this->postJson('/api/auth/employee-login', ['username' => 'joy.staff', 'password' => 'Staff-pass1', 'device_id' => 'tablet'])
            ->assertStatus(409)->assertJson(['code' => 'session_active']);
        $this->asToken($first, '/api/auth/employee-me')->assertOk();

        // …and takes over only when the person chooses to
        $second = $this->staffLogin(['device_id' => 'tablet', 'continue_here' => true]);

        $this->asToken($first, '/api/auth/employee-me')
            ->assertStatus(401)->assertJson(['code' => 'session_replaced']);
        $this->asToken($second, '/api/auth/employee-me')->assertOk();
    }

    public function test_staff_signing_in_again_on_the_same_browser_are_not_asked(): void
    {
        $this->staff();
        $this->staffLogin();

        $this->staffLogin();
    }

    public function test_a_staff_sign_in_left_idle_does_not_block_another_browser(): void
    {
        $this->staff();
        $this->staffLogin();
        $this->travel(16)->minutes();

        $this->staffLogin(['device_id' => 'tablet']);
    }

    public function test_staff_of_a_suspended_shop_are_told_why(): void
    {
        $this->staff();
        $token = $this->staffLogin();

        app(AccountSuspensionService::class)->suspend(User::find(1), null, 'Fake documents found');

        $this->asToken($token, '/api/auth/employee-me')
            ->assertStatus(401)
            ->assertJson(['code' => 'session_ended', 'message' => EndedSessions::MSG_SUSPENDED_STORE]);
    }

    public function test_a_working_sign_in_on_the_wrong_kind_of_page_is_not_a_sign_out(): void
    {
        $this->staff();
        $token = $this->staffLogin();

        $this->asToken($token, '/api/auth/me')
            ->assertStatus(401)->assertJson(['code' => 'wrong_account_type']);
        $this->asToken($token, '/api/auth/employee-me')->assertOk();
    }

    public function test_an_expired_session_keeps_saying_so(): void
    {
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer']);
        $token = $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'Secret-pass1'])->assertOk()->json('token');

        $this->travel(31)->minutes();
        $this->asToken($token, '/api/auth/session')->assertStatus(401)->assertJson(['code' => 'session_expired']);
        // Later requests from the same tab still get the reason
        $this->asToken($token, '/api/auth/session')->assertStatus(401)->assertJson(['code' => 'session_expired']);
    }
}
