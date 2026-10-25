<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * The account owner (the first admin) is the only one who can edit or remove
 * administrators, and nobody else can turn the owner off, demote them or set
 * their password.
 */
class AdminOwnerTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    private int $superRole;

    private int $opsRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        foreach (['surname' => 'string', 'username' => 'string', 'admin_role_id' => 'unsignedBigInteger', 'is_suspended' => 'boolean', 'is_verified' => 'boolean', 'last_seen_at' => 'timestamp'] as $column => $type) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $t) => $type === 'boolean' ? $t->boolean($column)->default(false) : $t->{$type}($column)->nullable());
            }
        }
        Schema::dropIfExists('admin_roles');
        Schema::create('admin_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('description')->nullable();
            $t->json('permissions');
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        // What the administrators list reads: last sign-in and recent actions
        Schema::dropIfExists('login_audit_logs');
        Schema::create('login_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('logged_in_at')->nullable();
        });
        Schema::dropIfExists('admin_audit_logs');
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
        (require base_path('database/migrations/2026_10_25_300000_add_admin_owner.php'))->up();
        $this->superRole = DB::table('admin_roles')->insertGetId(['name' => 'Super admin', 'permissions' => json_encode(['*']), 'is_system' => true]);
        $this->opsRole = DB::table('admin_roles')->insertGetId(['name' => 'Operations', 'permissions' => json_encode(['dashboard.view']), 'is_system' => false]);
    }

    private function admin(string $username, string $name, int $role, bool $owner = false): User
    {
        $user = User::create(['name' => $name, 'surname' => 'Test', 'username' => $username, 'email' => "{$username}@example.com", 'password' => Hash::make('x'), 'role' => User::ROLE_ADMIN]);
        $user->forceFill(['admin_role_id' => $role, 'is_admin_owner' => $owner])->save();

        return $user;
    }

    private function asAdmin(User $admin): static
    {
        $this->app['auth']->forgetGuards();

        return $this->signIn($admin);
    }

    public function test_the_first_admin_becomes_the_owner(): void
    {
        $first = User::create(['name' => 'Matthew', 'surname' => 'Javier', 'username' => 'admin', 'email' => 'admin@admin.local', 'password' => 'x', 'role' => User::ROLE_ADMIN]);
        $this->admin('marie', 'Marie', $this->superRole);
        DB::table('users')->update(['is_admin_owner' => false]);

        (require base_path('database/migrations/2026_10_25_300000_add_admin_owner.php'))->up();

        $this->assertSame([$first->id], User::where('is_admin_owner', true)->pluck('id')->all());
    }

    public function test_only_the_owner_edits_and_removes_administrators(): void
    {
        $owner = $this->admin('admin', 'Matthew', $this->superRole, true);
        $marie = $this->admin('marie', 'Marie', $this->superRole);
        $ops = $this->admin('ops', 'Olive', $this->opsRole);

        // Another super admin can't edit details or remove anyone
        $this->asAdmin($marie)->putJson("/api/admin/security/admins/{$ops->id}", ['name' => 'Changed'])->assertForbidden();
        $this->asAdmin($marie)->deleteJson("/api/admin/security/admins/{$ops->id}")->assertForbidden();

        // The owner can
        $this->asAdmin($owner)->putJson("/api/admin/security/admins/{$ops->id}", ['name' => 'Olivia', 'username' => 'olivia', 'email' => 'Olivia@Example.com'])->assertOk();
        $this->assertSame(['Olivia', 'olivia', 'olivia@example.com'], array_values($ops->fresh()->only(['name', 'username', 'email'])));

        $this->asAdmin($owner)->deleteJson("/api/admin/security/admins/{$ops->id}")->assertOk();
        $this->assertNull(User::find($ops->id));
    }

    public function test_only_the_owner_edits_the_owners_own_account(): void
    {
        $owner = $this->admin('admin', 'Matthew', $this->superRole, true);
        $marie = $this->admin('marie', 'Marie', $this->superRole);

        $this->asAdmin($marie)->putJson("/api/admin/security/admins/{$owner->id}", ['email' => 'taken-over@example.com'])->assertForbidden();
        $this->assertSame('admin@example.com', $owner->fresh()->email);

        // Matthew can move to a real inbox
        $this->asAdmin($owner)->putJson("/api/admin/security/admins/{$owner->id}", ['name' => 'Matthew', 'surname' => 'Javier', 'username' => 'admin', 'email' => 'Matthew@Example.com'])->assertOk();
        $this->assertSame('matthew@example.com', $owner->fresh()->email);
    }

    public function test_an_edit_cannot_take_another_accounts_username_or_email(): void
    {
        $owner = $this->admin('admin', 'Matthew', $this->superRole, true);
        $marie = $this->admin('marie', 'Marie', $this->superRole);

        $this->asAdmin($owner)->putJson("/api/admin/security/admins/{$marie->id}", ['username' => 'ADMIN', 'email' => 'Admin@Example.com'])
            ->assertStatus(422)->assertJsonValidationErrors(['username', 'email']);
    }

    public function test_nobody_else_can_turn_off_demote_remove_or_reset_the_owner(): void
    {
        $owner = $this->admin('admin', 'Matthew', $this->superRole, true);
        $marie = $this->admin('marie', 'Marie', $this->superRole);

        $this->asAdmin($marie)->postJson("/api/admin/security/admins/{$owner->id}/active", ['active' => false])->assertStatus(422);
        $this->asAdmin($marie)->putJson("/api/admin/security/admins/{$owner->id}", ['admin_role_id' => $this->opsRole])->assertStatus(422);
        $this->asAdmin($marie)->postJson("/api/admin/security/admins/{$owner->id}/password", ['password' => 'Brand-new-pass1'])->assertForbidden();
        $this->asAdmin($owner)->deleteJson("/api/admin/security/admins/{$owner->id}")->assertStatus(422);

        $fresh = $owner->fresh();
        $this->assertFalse((bool) $fresh->is_suspended);
        $this->assertSame($this->superRole, (int) $fresh->admin_role_id);
        $this->assertTrue(Hash::check('x', $fresh->password));

        // The owner can still change their own password
        $this->asAdmin($owner)->postJson("/api/admin/security/admins/{$owner->id}/password", ['password' => 'Brand-new-pass1'])->assertOk();
    }

    public function test_the_admin_list_says_who_the_owner_is(): void
    {
        $owner = $this->admin('admin', 'Matthew', $this->superRole, true);
        $this->admin('marie', 'Marie', $this->superRole);

        $me = $this->asAdmin($owner)->getJson('/api/admin/security/me')->assertOk()->json('data');
        $this->assertTrue($me['is_owner']);
        $list = collect($this->asAdmin($owner)->getJson('/api/admin/security/admins')->assertOk()->json('data'));
        $this->assertSame([true, false], $list->sortBy('id')->pluck('is_owner')->values()->all());
    }
}
