<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorResubmissionTest extends TestCase
{
    private const TOKEN = 'resubmit-token';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['cloudinary.cloud_url' => null, 'cloudinary.cloud_name' => null]);

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('role')->nullable();
            $t->string('password')->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_applications', function (Blueprint $t) {
            $t->id();
            $t->string('application_id')->nullable();
            $t->string('email')->nullable();
            $t->string('store_name')->nullable();
            $t->string('owner_name')->nullable();
            $t->string('status')->nullable();
            $t->string('resubmission_status')->nullable();
            // Profile flags the model refreshes on every save
            foreach (['payment', 'product', 'delivery'] as $part) {
                $t->boolean("{$part}_details_completed")->nullable();
            }
            $t->boolean('profile_fully_completed')->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_application_resubmission_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_application_id');
            $t->string('token_hash', 64);
            $t->string('status')->default('requested');
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->unsignedBigInteger('requested_by')->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_application_resubmissions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('resubmission_request_id');
            $t->unsignedBigInteger('vendor_application_id');
            $t->string('field_name');
            $t->text('rejection_reason')->nullable();
            $t->longText('original_value')->nullable();
            $t->longText('resubmitted_value')->nullable();
            $t->string('status')->default('pending_resubmission');
            $t->timestamp('requested_at')->nullable();
            $t->timestamp('resubmitted_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamps();
        });
    }

    private function requestFor(string $field): int
    {
        $appId = DB::table('vendor_applications')->insertGetId([
            'application_id' => 'VEN-1', 'email' => 'shop@example.com', 'store_name' => 'Flux Flower Store',
            'owner_name' => 'Mark Landingin', 'status' => 'approved',
        ]);
        $requestId = DB::table('vendor_application_resubmission_requests')->insertGetId([
            'vendor_application_id' => $appId, 'token_hash' => hash('sha256', self::TOKEN), 'expires_at' => now()->addDays(7),
        ]);

        return DB::table('vendor_application_resubmissions')->insertGetId([
            'resubmission_request_id' => $requestId, 'vendor_application_id' => $appId,
            'field_name' => $field, 'rejection_reason' => 'Blurry', 'status' => 'pending_resubmission',
        ]);
    }

    public function test_page_gets_the_limits_to_check_files_against(): void
    {
        $this->requestFor('barangay_clearance_path');

        $this->getJson('/api/vendor/resubmission/' . self::TOKEN)
            ->assertOk()
            ->assertJsonPath('store_name', 'Flux Flower Store')
            ->assertJsonPath('items.0.max', 12288)
            ->assertJsonPath('items.0.mimes', 'jpg,jpeg,png,pdf');
    }

    public function test_text_correction_is_saved_for_review(): void
    {
        $itemId = $this->requestFor('dti_number');

        $this->postJson('/api/vendor/resubmission/' . self::TOKEN, ['fields' => ['dti_number' => '1234567']])
            ->assertOk();

        $this->assertSame('resubmitted', DB::table('vendor_application_resubmissions')->find($itemId)->status);
        $this->assertSame('pending_review', DB::table('vendor_applications')->value('resubmission_status'));
    }

    public function test_too_large_file_is_explained_in_plain_words(): void
    {
        $this->requestFor('barangay_clearance_path');

        $response = $this->post('/api/vendor/resubmission/' . self::TOKEN, [
            'fields' => ['barangay_clearance_path' => UploadedFile::fake()->create('clearance.pdf', 13000, 'application/pdf')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame('The Barangay Clearance must be 12 MB or smaller.', $response->json('errors')['fields.barangay_clearance_path'][0]);
    }

    public function test_pdfs_stop_at_the_storage_limit_of_10_mb(): void
    {
        $this->requestFor('barangay_clearance_path');

        $response = $this->post('/api/vendor/resubmission/' . self::TOKEN, [
            'fields' => ['barangay_clearance_path' => UploadedFile::fake()->create('clearance.pdf', 11000, 'application/pdf')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertStringStartsWith('PDFs can be up to 10 MB.', $response->json('errors')['fields.barangay_clearance_path'][0]);
    }

    public function test_failed_upload_leaves_nothing_half_submitted(): void
    {
        $itemId = $this->requestFor('barangay_clearance_path');

        $response = $this->post('/api/vendor/resubmission/' . self::TOKEN, [
            'fields' => ['barangay_clearance_path' => UploadedFile::fake()->image('clearance.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(502);

        $this->assertStringStartsWith("We couldn't save your Barangay Clearance.", $response->json('message'));

        $this->assertSame('pending_resubmission', DB::table('vendor_application_resubmissions')->find($itemId)->status);
        $this->assertNull(DB::table('vendor_applications')->value('resubmission_status'));
    }
}
