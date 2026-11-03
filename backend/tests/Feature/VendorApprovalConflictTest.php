<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\VendorApplicationController;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Applicants who already have a BloomCraft account (usually as a customer)
 * can be approved: that account becomes the vendor account. Only an admin
 * account, or one already running another shop, blocks approval.
 */
class VendorApprovalConflictTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('contact_number')->nullable()->unique();
            $t->string('password')->nullable();
            $t->string('role')->default('customer');
            $t->json('vendor_data')->nullable();
            $t->timestamps();
        });
        Schema::dropIfExists('vendor_applications');
        Schema::create('vendor_applications', function (Blueprint $t) {
            $t->id();
            $t->string('store_name')->nullable();
            $t->string('email')->nullable();
            $t->string('contact_number')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });
    }

    private function conflict(VendorApplication $application): ?string
    {
        $method = new \ReflectionMethod(VendorApplicationController::class, 'detectApprovalConflict');

        return $method->invoke(app(VendorApplicationController::class), $application);
    }

    private function application(array $values = []): VendorApplication
    {
        $id = DB::table('vendor_applications')->insertGetId(array_merge([
            'store_name' => 'The Flower Room', 'email' => 'owner@example.com', 'contact_number' => '0917 123 4567', 'status' => 'pending',
        ], $values));

        return VendorApplication::findOrFail($id);
    }

    private function user(array $values): void
    {
        DB::table('users')->insert(array_merge(['name' => 'Someone', 'role' => 'customer'], $values));
    }

    public function test_a_new_email_can_be_approved(): void
    {
        $this->assertNull($this->conflict($this->application()));
    }

    public function test_an_applicant_with_a_customer_account_can_be_approved(): void
    {
        $this->user(['email' => 'Owner@Example.com', 'contact_number' => '0917 123 4567']);

        $this->assertNull($this->conflict($this->application()));
    }

    public function test_an_admin_email_cannot_become_a_shop(): void
    {
        $this->user(['email' => 'owner@example.com', 'role' => 'admin']);

        $this->assertStringContainsString('admin account', $this->conflict($this->application()));
    }

    public function test_an_email_running_another_shop_is_refused(): void
    {
        $other = $this->application(['store_name' => 'Petal & Stem', 'email' => 'old@example.com', 'status' => 'approved']);
        $this->user(['email' => 'owner@example.com', 'role' => 'vendor', 'vendor_data' => json_encode(['application_id' => $other->id])]);

        $this->assertStringContainsString('Petal & Stem', $this->conflict($this->application()));
    }

    public function test_a_phone_number_used_by_someone_else_blocks_a_new_account(): void
    {
        $this->user(['email' => 'someone@example.com', 'contact_number' => '0917 123 4567']);

        $this->assertStringContainsString('phone number', $this->conflict($this->application()));
    }
}
