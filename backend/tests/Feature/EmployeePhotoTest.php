<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/** The shop owner (or HR) sets and removes an employee's profile photo. */
class EmployeePhotoTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
    }

    public function test_only_pictures_are_accepted_and_photos_can_be_removed(): void
    {
        $vendor = $this->shopOwner();
        $profile = $this->profile($vendor, ['avatar' => 'https://example.com/old.jpg']);

        $this->signIn($vendor)
            ->post("/api/employees-info/{$profile->id}/photo", ['photo' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.photo.0', 'Choose a JPG, PNG or WebP picture.');
        $this->post("/api/employees-info/{$profile->id}/photo", ['photo' => UploadedFile::fake()->image('big.jpg')->size(6000)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.photo.0', 'Choose a photo under 5 MB.');

        $this->deleteJson("/api/employees-info/{$profile->id}/photo")->assertOk()->assertJsonPath('data.avatar_url', null);
        $this->assertNull($profile->fresh()->avatar);
    }

    public function test_another_shop_and_staff_without_hr_cannot_touch_it(): void
    {
        $vendor = $this->shopOwner();
        $profile = $this->profile($vendor, ['avatar' => 'https://example.com/keep.jpg']);

        $other = $this->shopOwner();
        $this->signIn($other)->deleteJson("/api/employees-info/{$profile->id}/photo")->assertNotFound();

        $this->app['auth']->forgetGuards();
        $cashier = $this->hrStaff($vendor); // attendance permissions only
        $this->signIn($cashier)->deleteJson("/api/employees-info/{$profile->id}/photo")->assertForbidden();

        $this->assertSame('https://example.com/keep.jpg', $profile->fresh()->avatar);
    }
}
