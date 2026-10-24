<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/** Finance reviewers and the shop owner can open funding requests from the Finance side. */
class FinanceFundingRequestAccessTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        DB::statement('DROP TABLE IF EXISTS funding_requests');
        foreach (['2026_02_07_085208_create_funding_requests_table', '2026_03_10_065051_add_linked_order_id_to_funding_requests_table',
            '2026_04_02_120100_add_requester_and_approver_to_funding_requests_table', '2026_04_05_110000_add_payment_status_to_funding_requests_table'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
    }

    private function staff($vendor, string $module): Employee
    {
        $e = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Staff ' . fake()->firstName(), 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'status' => 'Active']);
        DB::table('employee_module_permissions')->insert([
            'owner_id' => $vendor->id, 'employee_id' => $e->id, 'module' => $module, 'permission' => 'view',
            'access' => 'granular', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $e;
    }

    private function request($vendor, Employee $requester): int
    {
        $columns = collect(DB::select('PRAGMA table_info(funding_requests)'));
        $row = ['owner_id' => $vendor->id, 'requester_id' => $requester->id, 'created_at' => now(), 'updated_at' => now()];
        // Fill the other required columns with something that fits their type
        foreach ($columns as $c) {
            if ($c->notnull && $c->dflt_value === null && ! $c->pk && ! array_key_exists($c->name, $row)) {
                $row[$c->name] = str_contains(strtolower($c->type), 'int') || str_contains(strtolower($c->type), 'numeric') || str_contains(strtolower($c->type), 'decimal') ? 1
                    : (str_contains(strtolower($c->type), 'date') ? now()->toDateString() : 'x');
            }
        }
        $row['finance_request_id'] = 'FR-2026-0001';

        Schema::disableForeignKeyConstraints();
        $id = DB::table('funding_requests')->insertGetId($row);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    public function test_finance_staff_and_the_owner_can_open_a_request(): void
    {
        $vendor = $this->shopOwner();
        $buyer = $this->staff($vendor, 'inventory_funding');
        $finance = $this->staff($vendor, 'funding_requests');
        $hr = $this->staff($vendor, 'hr_dashboard');
        $id = $this->request($vendor, $buyer);

        $this->signIn($finance)->getJson("/api/finance/funding-requests/{$id}")->assertOk()->assertJsonPath('data.finance_request_id', 'FR-2026-0001');
        $this->app['auth']->forgetGuards();
        $this->signIn($hr)->getJson("/api/finance/funding-requests/{$id}")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->signIn($vendor)->getJson('/api/finance/funding-requests')->assertOk()->assertJsonCount(1, 'data');
        $this->signIn($vendor)->getJson("/api/finance/funding-requests/{$id}")->assertOk();

        // Another shop's request stays hidden
        $other = $this->shopOwner();
        $this->app['auth']->forgetGuards();
        $this->signIn($other)->getJson("/api/finance/funding-requests/{$id}")->assertNotFound();
    }
}
