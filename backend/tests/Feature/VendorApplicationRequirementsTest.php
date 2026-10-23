<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The vendor application asks for what a Philippine business is registered
 * with: one of DTI / SEC / CDA depending on the business structure, BIR Form
 * 2303 and TIN, a business permit that hasn't expired, and a valid ID type.
 */
class VendorApplicationRequirementsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('vendor_applications');
        Schema::create('vendor_applications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    private function application(array $override = []): array
    {
        $doc = fn ($name) => UploadedFile::fake()->create($name, 120, 'application/pdf');

        return array_merge([
            'store_name' => 'Petal & Stem',
            'store_description' => 'Hand-tied bouquets',
            'business_type' => 'individual',
            'registered_business_name' => 'Petal and Stem Flower Shop',
            'store_address' => 'Imus, Cavite',
            'physical_store_address' => 'Imus, Cavite',
            'store_latitude' => 14.42,
            'store_longitude' => 120.94,
            'service_areas' => 'Cavite',
            'operating_hours' => 'Mon–Sat 8 AM–6 PM',
            'operating_schedules' => '[]',
            'owner_name' => 'Camille Reyes',
            'position' => 'Owner',
            'contact_number' => '0917 123 4567',
            'email' => 'camille@example.com',
            'government_id_type' => 'philsys',
            'government_id_number' => '1234-5678-9012-3456',
            'government_id' => $doc('id.pdf'),
            'selfie_with_id' => UploadedFile::fake()->image('selfie.jpg'),
            'proof_of_address' => $doc('address.pdf'),
            'dti_number' => '3456789',
            'business_registration' => $doc('dti.pdf'),
            'barangay_clearance_number' => 'BC-2026-0142',
            'barangay_clearance' => $doc('barangay.pdf'),
            'mayor_permit_number' => 'BP-2026-00871',
            'mayor_permit' => $doc('permit.pdf'),
            'mayor_permit_valid_until' => now()->addMonths(3)->toDateString(),
            'bir_tin' => '123-456-789-00000',
            'bir_certificate' => $doc('2303.pdf'),
            'accept_terms' => '1',
            'accept_vendor_agreement' => '1',
            'accept_data_privacy' => '1',
            'confirm_accuracy' => '1',
            'application_date' => now()->toDateString(),
        ], $override);
    }

    /** Field names that failed validation */
    private function failing(array $override = [], array $without = []): array
    {
        $data = $this->application($override);
        foreach ($without as $key) {
            unset($data[$key]);
        }
        $res = $this->post('/api/vendor/register', $data, ['Accept' => 'application/json']);

        return $res->status() === 422 ? array_keys($res->json('errors') ?? []) : [];
    }

    public function test_a_sole_proprietor_needs_dti_but_not_sec(): void
    {
        $this->assertSame(['operating_schedules'], $this->failing(), 'only the empty schedule should fail');
        $this->assertContains('dti_number', $this->failing(['dti_number' => '']));
    }

    public function test_partnerships_and_corporations_need_sec_and_cooperatives_need_cda(): void
    {
        $this->assertContains('sec_number', $this->failing(['business_type' => 'corporation']));
        $this->assertNotContains('dti_number', $this->failing(['business_type' => 'corporation']));
        $this->assertNotContains('sec_number', $this->failing(['business_type' => 'partnership', 'sec_number' => 'CS201912345']));
        $this->assertContains('cda_number', $this->failing(['business_type' => 'cooperative']));
    }

    public function test_bir_form_2303_registration_certificate_and_id_type_are_required(): void
    {
        $missing = $this->failing([], ['bir_certificate', 'business_registration', 'government_id_type', 'registered_business_name']);
        foreach (['bir_certificate', 'business_registration', 'government_id_type', 'registered_business_name'] as $field) {
            $this->assertContains($field, $missing);
        }
    }

    public function test_an_expired_business_permit_is_refused(): void
    {
        $this->assertContains('mayor_permit_valid_until', $this->failing(['mayor_permit_valid_until' => now()->subDay()->toDateString()]));
    }

    public function test_tin_and_phone_follow_philippine_formats(): void
    {
        $this->assertNotContains('bir_tin', $this->failing(['bir_tin' => '123456789']));
        $this->assertContains('bir_tin', $this->failing(['bir_tin' => '12345']));
        $this->assertNotContains('contact_number', $this->failing(['contact_number' => '+63 917 123 4567']));
        $this->assertNotContains('contact_number', $this->failing(['contact_number' => '(046) 123 4567']));
        $this->assertContains('contact_number', $this->failing(['contact_number' => '12345']));
    }
}
