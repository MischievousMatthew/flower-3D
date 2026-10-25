<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Vendors, customers and staff never see programmer errors; admins find the
 * real error under the code the person was shown.
 */
class FriendlyErrorsTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    private const SQL_ERROR = 'SQLSTATE[42703]: Undefined column: 7 ERROR: column "tax_amount" does not exist (Connection: pgsql, SQL: select "tax_amount" from "orders" where "id" in (21))';

    protected function setUp(): void
    {
        parent::setUp();
        // Debug mode on, like a misconfigured server: still nothing technical gets out
        config(['app.debug' => true]);
        $this->createAttendanceTables();
        if (! Schema::hasColumn('users', 'admin_role_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('admin_role_id')->nullable());
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
        Schema::dropIfExists('api_error_logs');
        Schema::create('api_error_logs', function (Blueprint $t) {
            $t->id();
            $t->string('method', 10);
            $t->string('path', 255);
            $t->string('action', 190)->nullable();
            $t->unsignedSmallInteger('status_code');
            $t->string('exception', 190)->nullable();
            $t->string('message', 500)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->unsignedInteger('duration_ms')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        (require base_path('database/migrations/2026_10_25_100000_add_reference_to_api_error_logs.php'))->up();

        Route::middleware(['api', 'token.auth'])->prefix('api/_test')->group(function () {
            Route::get('/crash', fn () => throw new QueryException('pgsql', 'select "tax_amount" from "orders"', [], new \PDOException(self::SQL_ERROR)));
            Route::post('/caught', fn () => response()->json(['success' => false, 'message' => 'Failed to save', 'error' => self::SQL_ERROR], 500));
            Route::post('/caught-ok-status', fn () => response()->json(['success' => false, 'message' => 'Error: Call to undefined method App\\Models\\Order::taxes()'], 422));
            Route::post('/readable', fn () => response()->json(['success' => false, 'message' => 'Only 3 roses are left in stock.'], 422));
            Route::get('/upload', fn () => response()->json(['success' => true, 'file' => 'https://cdn.example.com/a.jpg', 'line' => 2]));
            Route::get('/missing', fn () => abort(404));
        });
        Route::middleware(['api', 'auth'])->get('api/_test/private', fn () => 'secret');
    }

    public function test_a_crash_shows_a_plain_message_and_a_code_and_admins_get_the_real_error(): void
    {
        if (! Schema::hasColumn('vendor_applications', 'store_name')) {
            Schema::table('vendor_applications', fn (Blueprint $t) => $t->string('store_name')->nullable());
        }
        $vendor = $this->shopOwner();
        DB::table('vendor_applications')->where('email', $vendor->email)->update(['store_name' => 'Petal & Stem']);

        $res = $this->signIn($vendor)->getJson('/api/_test/crash')->assertStatus(500);

        $reference = $res->json('reference');
        $this->assertMatchesRegularExpression('/^BC-[A-Z0-9]{6}$/', $reference);
        $this->assertSame("We couldn't load this right now. Please try again. If it keeps happening, contact support and mention code {$reference}.", $res->json('message'));
        $this->assertStringNotContainsString('SQLSTATE', $res->getContent());
        $this->assertStringNotContainsString('.php', $res->getContent());
        $this->assertNull($res->json('trace'));

        $log = DB::table('api_error_logs')->where('reference', $reference)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('column "tax_amount" does not exist', $log->message);
        $this->assertSame('vendor', $log->account_type);
        $this->assertSame('Petal & Stem', $log->account_name);
        $this->assertStringContainsString('FriendlyErrorsTest.php:', $log->location);
        $this->assertSame($res->json('message'), $log->shown_message);
    }

    public function test_errors_that_code_caught_and_passed_on_are_hidden_too(): void
    {
        $customer = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x', 'role' => User::ROLE_CUSTOMER]);

        $res = $this->signIn($customer)->postJson('/api/_test/caught')->assertStatus(500);
        $this->assertStringStartsWith("We couldn't finish that.", $res->json('message'));
        $this->assertNull($res->json('error'));

        // Even when the status looks ordinary
        $res2 = $this->signIn($customer)->postJson('/api/_test/caught-ok-status')->assertStatus(422);
        $this->assertStringStartsWith("We couldn't finish that.", $res2->json('message'));

        $logs = DB::table('api_error_logs')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['customer', 'customer'], $logs->pluck('account_type')->all());
        // Customers stay unnamed in the admin log
        $this->assertNull($logs[0]->account_name);
        $this->assertStringContainsString('SQLSTATE', $logs[0]->message);
    }

    public function test_readable_messages_and_ordinary_data_are_left_alone(): void
    {
        $customer = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x', 'role' => User::ROLE_CUSTOMER]);

        $this->signIn($customer)->postJson('/api/_test/readable')->assertStatus(422)->assertJsonPath('message', 'Only 3 roses are left in stock.');
        $this->signIn($customer)->getJson('/api/_test/upload')->assertOk()->assertJsonPath('file', 'https://cdn.example.com/a.jpg')->assertJsonPath('line', 2);
        $this->assertSame(0, DB::table('api_error_logs')->count());
    }

    public function test_framework_wording_is_replaced_with_plain_words(): void
    {
        $this->getJson('/api/_test/private')->assertStatus(401)->assertJsonPath('message', 'Please sign in again to continue.');

        $customer = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x', 'role' => User::ROLE_CUSTOMER]);
        $this->signIn($customer)->getJson('/api/_test/missing')->assertStatus(404)->assertJsonPath('message', "We couldn't find that. It may have been moved or removed.");
        $this->assertSame(0, DB::table('api_error_logs')->count());
    }

    public function test_admins_can_look_up_the_code_someone_quoted(): void
    {
        $vendor = $this->shopOwner();
        $reference = $this->signIn($vendor)->getJson('/api/_test/crash')->json('reference');
        $this->app['auth']->forgetGuards();

        $roleId = DB::table('admin_roles')->insertGetId(['name' => 'Super admin', 'permissions' => json_encode(['*']), 'is_system' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x', 'role' => User::ROLE_ADMIN]);
        $admin->forceFill(['admin_role_id' => $roleId])->save();

        // Typed without the "BC-" and in lower case still works
        $found = $this->signIn($admin)->getJson('/api/admin/security/api-errors?reference=' . strtolower(substr($reference, 3)))->assertOk()->json('data.lookup');
        $this->assertCount(1, $found);
        $this->assertSame($reference, $found[0]['reference']);
        $this->assertSame('vendor', $found[0]['account']);
        $this->assertSame('QueryException', $found[0]['exception']);
        // The admin view keeps the cause but drops the query and its values
        $this->assertStringContainsString('column "tax_amount" does not exist', $found[0]['message']);
        $this->assertStringNotContainsString('SQL:', $found[0]['message']);
    }
}
