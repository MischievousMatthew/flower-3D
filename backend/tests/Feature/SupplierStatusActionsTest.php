<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierStatusActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('vendor');
            $table->rememberToken()->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('email')->unique();
            $table->string('phone', 20);
            $table->text('address');
            $table->enum('status', ['active', 'inactive', 'blacklisted'])->default('active');
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('email')->unique();
            $table->string('phone', 20);
            $table->text('address');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function test_vendor_can_activate_supplier(): void
    {
        $vendor = $this->createVendor('activate@example.com');
        $supplier = $this->createSupplier($vendor->id, 'inactive', 'activate-supplier@example.com');

        Sanctum::actingAs($vendor);

        $this->patchJson("/api/procurement/supply-chain/suppliers/{$supplier->id}/activate")
            ->assertOk()
            ->assertJsonPath('status', 'active');

        $this->assertSame('active', $supplier->fresh()->status);
    }

    public function test_vendor_can_deactivate_supplier(): void
    {
        $vendor = $this->createVendor('deactivate@example.com');
        $supplier = $this->createSupplier($vendor->id, 'active', 'deactivate-supplier@example.com');

        Sanctum::actingAs($vendor);

        $this->patchJson("/api/procurement/supply-chain/suppliers/{$supplier->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('status', 'inactive');

        $this->assertSame('inactive', $supplier->fresh()->status);
    }

    public function test_vendor_can_blacklist_supplier(): void
    {
        $vendor = $this->createVendor('blacklist@example.com');
        $supplier = $this->createSupplier($vendor->id, 'active', 'blacklist-supplier@example.com');

        Sanctum::actingAs($vendor);

        $this->patchJson("/api/procurement/supply-chain/suppliers/{$supplier->id}/blacklist")
            ->assertOk()
            ->assertJsonPath('status', 'blacklisted');

        $this->assertSame('blacklisted', $supplier->fresh()->status);
    }

    public function test_status_update_returns_404_for_supplier_owned_by_another_vendor(): void
    {
        $vendor = $this->createVendor('primary-owner@example.com');
        $otherVendor = $this->createVendor('other-owner@example.com');
        $supplier = $this->createSupplier($otherVendor->id, 'inactive', 'other-owner-supplier@example.com');

        Sanctum::actingAs($vendor);

        $this->patchJson("/api/procurement/supply-chain/suppliers/{$supplier->id}/activate")
            ->assertNotFound();
    }

    public function test_status_update_returns_404_for_missing_supplier(): void
    {
        $vendor = $this->createVendor('missing-owner@example.com');

        Sanctum::actingAs($vendor);

        $this->patchJson('/api/procurement/supply-chain/suppliers/999999/blacklist')
            ->assertNotFound();
    }

    public function test_nested_contact_routes_create_update_and_delete_the_target_contact(): void
    {
        $vendor = $this->createVendor('contacts-owner@example.com');
        $supplier = $this->createSupplier($vendor->id, 'active', 'contacts-supplier@example.com');

        Sanctum::actingAs($vendor);

        $createResponse = $this->postJson("/api/procurement/supply-chain/suppliers/{$supplier->id}/contacts", [
            'company_name' => 'Branch One',
            'contact_person' => 'Alice Contact',
            'email' => 'branch-one@example.com',
            'phone' => '09170000001',
            'address' => 'Manila',
            'status' => 'active',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('supplier_id', $supplier->id)
            ->assertJsonPath('contact_person', 'Alice Contact');

        $contactId = $createResponse->json('id');

        $this->putJson("/api/procurement/supply-chain/suppliers/{$supplier->id}/contacts/{$contactId}", [
            'contact_person' => 'Updated Contact',
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('id', $contactId)
            ->assertJsonPath('contact_person', 'Updated Contact')
            ->assertJsonPath('status', 'inactive');

        $this->assertDatabaseHas('supplier_contacts', [
            'id' => $contactId,
            'supplier_id' => $supplier->id,
            'contact_person' => 'Updated Contact',
            'status' => 'inactive',
        ]);

        $this->deleteJson("/api/procurement/supply-chain/suppliers/{$supplier->id}/contacts/{$contactId}")
            ->assertOk()
            ->assertJsonPath('message', 'Contact removed successfully.');

        $this->assertDatabaseMissing('supplier_contacts', [
            'id' => $contactId,
        ]);
    }

    private function createVendor(string $email): User
    {
        return User::query()->create([
            'name' => 'Vendor User',
            'email' => $email,
            'password' => 'password',
            'role' => 'vendor',
        ]);
    }

    private function createSupplier(int $ownerId, string $status, string $email): Supplier
    {
        return Supplier::query()->create([
            'owner_id' => $ownerId,
            'company_name' => 'Sample Supplier',
            'contact_person' => 'Supplier Contact',
            'email' => $email,
            'phone' => '09171234567',
            'address' => 'Quezon City',
            'status' => $status,
        ]);
    }
}
