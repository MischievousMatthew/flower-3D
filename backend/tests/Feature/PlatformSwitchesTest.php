<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Security → Platform settings: pausing checkout and closing sign-ups must
 * actually stop customers, not just change the switch on the admin page.
 */
class PlatformSwitchesTest extends TestCase
{
    use SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('users');

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->string('api_token', 80)->nullable()->unique();
            $table->timestamps();
        });
    }

    private function customer(): User
    {
        return User::forceCreate(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('secret-pass'), 'role' => 'customer']);
    }

    public function test_paused_checkout_blocks_starting_checkout(): void
    {
        PlatformSettings::save(['checkout_paused' => true, 'checkout_pause_message' => 'Back soon, we are restocking.']);

        $this->signIn($this->customer())
            ->postJson('/api/checkout/initialize', ['cart_item_ids' => [1]])
            ->assertStatus(503)
            ->assertJson(['success' => false, 'message' => 'Back soon, we are restocking.']);
    }

    public function test_paused_checkout_blocks_placing_an_order(): void
    {
        PlatformSettings::save(['checkout_paused' => true]);

        $this->signIn($this->customer())
            ->postJson('/api/checkout/create-order', [])
            ->assertStatus(503)
            ->assertJson(['success' => false]);
    }

    public function test_closed_registrations_block_customer_sign_up(): void
    {
        PlatformSettings::save(['registrations_open' => false]);

        $this->postJson('/api/auth/register', [])
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_closed_vendor_applications_block_vendor_sign_up(): void
    {
        PlatformSettings::save(['vendor_applications_open' => false]);

        $this->postJson('/api/vendor/register', [])
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_closed_registrations_do_not_send_a_sign_up_code(): void
    {
        PlatformSettings::save(['registrations_open' => false]);

        $this->postJson('/api/auth/send-otp', ['email' => 'new@example.com'])
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_anyone_can_see_what_is_open_right_now(): void
    {
        PlatformSettings::save([
            'registrations_open' => false,
            'vendor_applications_open' => false,
            'checkout_paused' => true,
            'checkout_pause_message' => 'Back soon, we are restocking.',
        ]);

        $this->getJson('/api/site/status')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => [
                'registrations_open' => false,
                'vendor_applications_open' => false,
                'checkout_paused' => true,
                'checkout_pause_message' => 'Back soon, we are restocking.',
            ]]);
    }

    public function test_the_status_shows_nothing_but_the_public_switches(): void
    {
        $data = $this->getJson('/api/site/status')->assertOk()->json('data');

        $this->assertSame(['registrations_open', 'vendor_applications_open', 'checkout_paused', 'checkout_pause_message'], array_keys($data));
    }

    public function test_the_admin_can_see_the_new_value_right_after_saving(): void
    {
        PlatformSettings::save(['checkout_paused' => true]);

        $this->assertTrue(PlatformSettings::get('checkout_paused'));
    }
}
