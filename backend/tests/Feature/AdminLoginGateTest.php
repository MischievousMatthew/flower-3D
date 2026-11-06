<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoginAuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesAuthTables;
use Tests\TestCase;

/**
 * Admins sign in on a page nothing links to, at a URL holding a secret key
 * that only the server knows (ADMIN_LOGIN_KEY). A wrong key looks like any
 * missing page, and the normal sign-in page refuses admin accounts with its
 * usual "doesn't match" answer, so neither gives anything away.
 */
class AdminLoginGateTest extends TestCase
{
    use CreatesAuthTables;

    private const KEY = 'k7Qm2Vr8Hf4Xc6PzT9wLs3Nd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthTables();
        $this->mock(LoginAuditService::class)->shouldIgnoreMissing();
        Cache::flush();
        config(['app.admin_login_key' => self::KEY]);
        User::forceCreate(['name' => 'Ada', 'username' => 'ada', 'email' => 'ada@example.com', 'password' => Hash::make('Admin-pass1'), 'role' => 'admin']);
        User::forceCreate(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => Hash::make('Secret-pass1'), 'role' => 'customer']);
    }

    private function gate(string $key, string $username, string $password)
    {
        return $this->postJson("/api/admin-gate/{$key}/login", ['username' => $username, 'password' => $password]);
    }

    public function test_the_right_key_opens_the_page_and_a_wrong_one_looks_like_a_missing_page(): void
    {
        $this->getJson('/api/admin-gate/' . self::KEY)->assertNoContent();
        $this->getJson('/api/admin-gate/wrong-key-wrong-key-wrong')->assertNotFound();
        $this->gate('wrong-key-wrong-key-wrong', 'ada', 'Admin-pass1')->assertNotFound();
    }

    public function test_an_admin_signs_in_with_the_key(): void
    {
        $res = $this->gate(self::KEY, 'ada', 'Admin-pass1')->assertOk();
        $this->assertNotEmpty($res->json('token'));
        $this->assertSame('admin', $res->json('user.role'));
    }

    public function test_other_accounts_are_refused_at_the_admin_page_as_if_the_password_were_wrong(): void
    {
        $this->gate(self::KEY, 'ana', 'Secret-pass1')->assertStatus(422)->assertJson(['code' => 'invalid_credentials'])->assertJsonMissingPath('token');
        $this->assertSame('admin_page', DB::table('login_attempts')->latest('id')->value('reason'));
    }

    public function test_the_normal_sign_in_page_refuses_admins_the_same_way_it_refuses_a_wrong_password(): void
    {
        $wrongPage = $this->postJson('/api/auth/login', ['username' => 'ada', 'password' => 'Admin-pass1'])->assertStatus(422)->json();
        $wrongPassword = $this->postJson('/api/auth/login', ['username' => 'ada', 'password' => 'nope'])->assertStatus(422)->json();
        $this->assertSame($wrongPassword, $wrongPage);
        $this->assertArrayNotHasKey('token', $wrongPage);
        // Customers still sign in there
        $this->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'Secret-pass1'])->assertOk();
    }

    public function test_until_a_key_is_set_admins_keep_the_normal_page_and_the_hidden_one_stays_shut(): void
    {
        config(['app.admin_login_key' => null]);
        $this->postJson('/api/auth/login', ['username' => 'ada', 'password' => 'Admin-pass1'])->assertOk();
        $this->getJson('/api/admin-gate/anything-at-all-here-xyz')->assertNotFound();
        // A short key is treated as not set: it could be guessed
        config(['app.admin_login_key' => 'short']);
        $this->getJson('/api/admin-gate/short')->assertNotFound();
    }
}
