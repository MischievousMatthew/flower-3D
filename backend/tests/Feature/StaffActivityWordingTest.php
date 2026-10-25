<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/** The activity log says what staff did in plain words, sign-outs included. */
class StaffActivityWordingTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        Schema::dropIfExists('staff_activity_logs');
        Schema::create('staff_activity_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->string('actor_name', 120)->nullable();
            $t->string('action', 160);
            $t->string('area', 40);
            $t->string('method', 8);
            $t->unsignedSmallInteger('status');
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        // The logger switches itself off after a failure; start fresh
        (new \ReflectionProperty(\App\Http\Middleware\LogStaffActivity::class, 'disabled'))->setValue(null, false);
    }

    public function test_signing_out_is_logged_as_signed_out_and_old_entries_are_renamed(): void
    {
        $shop = $this->shopOwner();
        $aj = Employee::forceCreate(['owner_id' => $shop->id, 'name' => 'aj', 'email' => 'aj@example.com', 'password' => 'x', 'status' => 'Active']);
        $old = fn ($action, $area, $method = 'POST') => DB::table('staff_activity_logs')->insertGetId(['owner_id' => $shop->id, 'employee_id' => $aj->id, 'actor_name' => 'aj', 'action' => $action, 'area' => $area, 'method' => $method, 'status' => 200]);
        $old('Added to employee auth', 'Employee Auth');
        $books = $old('Added to vendor books', 'Vendor Books');
        $gone = $old('Removed something from vendor promotion: Summer sale', 'Vendor Promotion', 'DELETE');
        $kept = $old('Took someone off a shift', 'Schedules', 'DELETE');

        $this->signIn($aj)->postJson('/api/auth/employee-logout')->assertOk();
        $this->assertDatabaseHas('staff_activity_logs', ['employee_id' => $aj->id, 'action' => 'Signed out', 'area' => 'Account']);

        (require base_path('database/migrations/2026_10_25_000000_reword_staff_sign_out_logs.php'))->up();
        $this->assertSame(0, DB::table('staff_activity_logs')->where('action', 'Added to employee auth')->count());
        $this->assertSame(2, DB::table('staff_activity_logs')->where('action', 'Signed out')->count());
        $row = fn ($id) => DB::table('staff_activity_logs')->find($id);
        $this->assertSame(['Made a change in expenses', 'Expenses'], [$row($books)->action, $row($books)->area]);
        $this->assertSame(['Removed something in promotions: Summer sale', 'Promotions'], [$row($gone)->action, $row($gone)->area]);
        $this->assertSame('Took someone off a shift', $row($kept)->action);
    }

    public function test_code_names_never_reach_the_owner(): void
    {
        $this->assertSame('Expenses', \App\Http\Middleware\LogStaffActivity::areaFor('VendorBooksController'));
        $this->assertSame('Account', \App\Http\Middleware\LogStaffActivity::areaFor('EmployeeAuthController'));
        // Anything unlisted still reads as plain words, without "Vendor" or "Controller"
        $this->assertSame('Gift cards', \App\Http\Middleware\LogStaffActivity::areaFor('VendorGiftCardsController'));
    }
}
