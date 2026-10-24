<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Finance → Expenses: flowers, supplies, packaging, delivery, utilities and
 * rent recorded by category, filtered by date and category, with receipts and
 * documents attached; flower purchases count as stock, not operating expenses.
 */
class ExpensesTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    private const OCTOBER = '?from=2026-10-01&to=2026-10-31';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff(User $vendor, array $permissions): Employee
    {
        $employee = $this->financeStaff($vendor);
        foreach ($permissions as $p) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $employee->id, 'module' => 'expenses', 'permission' => $p, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $employee;
    }

    private function record($api, array $data, string $url = '/api/finance/expenses')
    {
        return $api->post($url, $data + ['description' => 'Something', 'amount' => 100, 'spent_on' => '2026-10-10'], ['Accept' => 'application/json']);
    }

    /** October: ₱5,000 of roses, ₱800 packaging, ₱600 delivery, ₱1,500 utilities, ₱8,000 rent; September: ₱900 packaging */
    private function shopWithExpenses(): array
    {
        $vendor = $this->shopOwner();
        $api = $this->signIn($this->staff($vendor, ['view', 'create']));
        foreach ([
            ['flowers', 'Roses from Dangwa', 5000, '2026-10-02'],
            ['packaging', 'Kraft paper and ribbons', 800, '2026-10-05'],
            ['delivery', 'Fuel', 600, '2026-10-08'],
            ['utilities', 'Meralco', 1500, '2026-10-15'],
            ['rent', 'October stall rent', 8000, '2026-10-01'],
            ['packaging', 'Boxes', 900, '2026-09-20'],
        ] as [$category, $description, $amount, $on]) {
            $this->record($api, compact('category', 'description', 'amount') + ['spent_on' => $on])->assertCreated();
        }

        return [$vendor, $api];
    }

    public function test_expenses_are_recorded_by_category_and_filtered_by_date_and_category(): void
    {
        [$vendor, $api] = $this->shopWithExpenses();

        $data = $api->getJson('/api/finance/expenses' . self::OCTOBER)->assertOk()->json('data');
        $this->assertCount(5, $data['expenses']);
        $this->assertEquals(15900, $data['total']);
        $this->assertEquals(900, $data['previous_total']);
        // Flowers are counted apart: they're stock bought to sell
        $this->assertEquals(5000, $data['stock_total']);
        $flowers = collect($data['by_category'])->firstWhere('category', 'flowers');
        $this->assertTrue($flowers['stock']);
        $this->assertSame('Flowers', $flowers['label']);
        $this->assertSame(['flowers', 'supplies', 'packaging', 'delivery', 'utilities', 'rent'], array_slice(array_column($data['categories'], 'value'), 0, 6));
        $this->assertSame(5, $data['without_files']);

        $packaging = $api->getJson('/api/finance/expenses' . self::OCTOBER . '&category=packaging')->json('data.expenses');
        $this->assertCount(1, $packaging);
        $this->assertSame('Kraft paper and ribbons', $packaging[0]['description']);
        // Rent on the 1st, roses on the 2nd, packaging on the 5th
        $this->assertCount(3, $api->getJson('/api/finance/expenses?from=2026-10-01&to=2026-10-06')->json('data.expenses'));
    }

    public function test_flower_purchases_are_stock_not_operating_expenses(): void
    {
        [$vendor] = $this->shopWithExpenses();

        $pl = $this->signIn($vendor)->getJson('/api/vendor/books/profit-loss' . self::OCTOBER)->assertOk()->json('data.current');

        // Packaging, delivery, utilities and rent
        $this->assertEquals(10900, $pl['operating_expenses']);
        $this->assertEquals(5000, $pl['stock_purchases']);
        $this->assertNotContains('flowers', array_column($pl['expenses_by_category'], 'category'));
    }

    public function test_receipts_must_be_photos_or_pdfs_and_at_most_five(): void
    {
        $vendor = $this->shopOwner();
        $api = $this->signIn($this->staff($vendor, ['view', 'create']));

        $this->record($api, ['category' => 'supplies', 'files' => [UploadedFile::fake()->create('notes.txt', 4, 'text/plain')]])
            ->assertStatus(422)->assertJsonValidationErrors('files.0');
        $six = array_map(fn ($i) => UploadedFile::fake()->create("r{$i}.pdf", 10, 'application/pdf'), range(1, 6));
        $this->record($api, ['category' => 'supplies', 'files' => $six])->assertStatus(422)->assertJsonValidationErrors('files');
        $this->assertSame(0, DB::table('vendor_expenses')->count());
    }

    public function test_attached_files_are_listed_and_can_be_removed_and_stay_private_to_the_shop(): void
    {
        [$vendor, $api] = $this->shopWithExpenses();
        $expenseId = DB::table('vendor_expenses')->where('category', 'utilities')->value('id');
        $fileId = DB::table('vendor_expense_files')->insertGetId(['owner_id' => $vendor->id, 'vendor_expense_id' => $expenseId, 'public_id' => 'expense-files/x/bill', 'format' => 'pdf', 'file_name' => 'meralco-oct.pdf', 'size' => 2048, 'created_at' => now(), 'updated_at' => now()]);

        $data = $api->getJson('/api/finance/expenses' . self::OCTOBER . '&category=utilities')->json('data');
        $this->assertSame([['id' => $fileId, 'name' => 'meralco-oct.pdf', 'size' => 2048, 'image' => false, 'format' => 'pdf']], $data['expenses'][0]['files']);

        // Another shop can't open it
        $this->signIn($this->shopOwner())->getJson("/api/vendor/books/expenses/{$expenseId}/files/{$fileId}/link")->assertNotFound();

        // Editing with the file ticked for removal deletes it (signed in afresh, as each real request is)
        $this->app['auth']->forgetGuards();
        $editor = $this->signIn($this->staff($vendor, ['view', 'edit']));
        $this->record($editor, ['category' => 'utilities', 'description' => 'Meralco', 'amount' => 1500, 'spent_on' => '2026-10-15', 'remove_files' => [$fileId]], "/api/finance/expenses/{$expenseId}")
            ->assertOk()->assertJsonPath('data.files', []);
        $this->assertSame(0, DB::table('vendor_expense_files')->count());
    }

    public function test_staff_need_the_right_expenses_permission_for_each_action(): void
    {
        [$vendor] = $this->shopWithExpenses();
        $expenseId = DB::table('vendor_expenses')->value('id');

        $this->signIn($this->financeStaff($vendor))->getJson('/api/finance/expenses')->assertForbidden();
        $viewer = $this->signIn($this->staff($vendor, ['view']));
        $viewer->getJson('/api/finance/expenses')->assertOk();
        $this->record($viewer, ['category' => 'rent'])->assertForbidden();
        $this->signIn($this->staff($vendor, ['view', 'create']))->deleteJson("/api/finance/expenses/{$expenseId}")->assertForbidden();

        $this->signIn($this->staff($vendor, ['view', 'delete']))->deleteJson("/api/finance/expenses/{$expenseId}")->assertOk();
        $this->assertSame(5, DB::table('vendor_expenses')->count());
    }
}
