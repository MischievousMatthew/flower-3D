<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorPaymentDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('vendor_applications');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->json('vendor_data')->nullable();
            $table->rememberToken()->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_id')->nullable();
            $table->string('status_token')->nullable();
            $table->string('email')->index();
            $table->string('status')->default('pending');
            $table->string('payout_method')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->text('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('billing_address')->nullable();
            $table->json('product_types')->nullable();
            $table->decimal('price_min', 10, 2)->nullable();
            $table->decimal('price_max', 10, 2)->nullable();
            $table->boolean('same_day_delivery')->nullable();
            $table->json('cutoff_times')->nullable();
            $table->string('delivery_handled_by')->nullable();
            $table->integer('max_orders_per_day')->nullable();
            $table->string('lead_time')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->boolean('payment_details_completed')->default(false);
            $table->boolean('product_details_completed')->default(false);
            $table->boolean('delivery_details_completed')->default(false);
            $table->boolean('profile_fully_completed')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_vendor_can_update_payment_details_with_large_numeric_account_number(): void
    {
        $user = $this->createVendorUser();
        $vendorApplication = $this->createApprovedVendorApplication($user);

        Sanctum::actingAs($user);

        $accountNumber = '091712345678901234567890';

        $response = $this->putJson('/api/vendor/profile/payment-details', [
            'payout_method'       => 'gcash',
            'account_holder_name' => 'Bernadet Landingin',
            'account_number'      => $accountNumber,
            'bank_name'           => '',
            'billing_address'     => 'Manila City',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.decrypted_account_number', $accountNumber)
            ->assertJsonPath('data.bank_name', 'Gcash');

        $vendorApplication->refresh();

        $this->assertSame($accountNumber, $vendorApplication->decrypted_account_number);
        $this->assertNotSame($accountNumber, $vendorApplication->getRawOriginal('account_number'));
        $this->assertSame('Gcash', $vendorApplication->bank_name);
        $this->assertTrue($vendorApplication->payment_details_completed);
    }

    public function test_missing_vendor_application_returns_404_instead_of_500(): void
    {
        $user = $this->createVendorUser();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/vendor/profile/payment-details', [
            'payout_method'       => 'bank',
            'account_holder_name' => 'Missing Vendor',
            'account_number'      => '1234567890',
            'bank_name'           => 'BDO',
            'billing_address'     => 'Quezon City',
        ]);

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Vendor application not found');
    }

    public function test_corrupted_account_number_does_not_crash_vendor_profile_serialization(): void
    {
        $user = $this->createVendorUser();
        $vendorApplication = $this->createApprovedVendorApplication($user);

        DB::table('vendor_applications')
            ->where('id', $vendorApplication->id)
            ->update(['account_number' => 'corrupted-ciphertext']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/vendor/profile');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.decrypted_account_number', null);
    }

    public function test_payment_details_update_survives_legacy_corrupted_profile_fields(): void
    {
        $user = $this->createVendorUser();
        $vendorApplication = $this->createApprovedVendorApplication($user);

        DB::table('vendor_applications')
            ->where('id', $vendorApplication->id)
            ->update([
                'account_number' => 'corrupted-ciphertext',
                'product_types'  => 'not-json',
                'cutoff_times'   => 'also-not-json',
            ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/vendor/profile/payment-details', [
            'payout_method'       => 'bank',
            'account_holder_name' => 'Vendor Name',
            'account_number'      => '12345678901234567890',
            'bank_name'           => 'BDO',
            'billing_address'     => 'Makati City',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bank_name', 'BDO')
            ->assertJsonMissingPath('data.product_types')
            ->assertJsonMissingPath('data.cutoff_times');
    }

    private function createVendorUser(): User
    {
        return User::query()->create([
            'name'     => 'Vendor User',
            'email'    => 'vendor@example.com',
            'password' => 'password',
            'role'     => 'vendor',
        ]);
    }

    private function createApprovedVendorApplication(User $user): VendorApplication
    {
        return VendorApplication::query()->create([
            'email'        => $user->email,
            'status'       => 'approved',
            'submitted_at' => now(),
        ]);
    }
}
