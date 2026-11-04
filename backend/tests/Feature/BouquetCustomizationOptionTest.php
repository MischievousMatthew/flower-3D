<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSubscriptionModuleAccess;
use App\Models\BouquetCustomizationOption;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BouquetCustomizationOptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('bouquet_customization_options');
        Schema::dropIfExists('vendor_applications');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('vendor_applications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('status')->default('pending');
            $table->json('bouquet_presets')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->string('api_token')->nullable();
            $table->timestamps();
        });

        (include database_path('migrations/2026_10_10_000001_create_bouquet_customization_options_table.php'))->up();
        (include database_path('migrations/2026_11_04_130000_add_pattern_to_bouquet_customization_options.php'))->up();

        $this->withoutMiddleware(EnsureSubscriptionModuleAccess::class);
    }

    private function vendor(string $email = 'vendor@example.com'): User
    {
        $vendor = new User();
        $vendor->forceFill([
            'name' => 'Vendor',
            'email' => $email,
            'password' => 'secret',
            'role' => 'vendor',
            'api_token' => hash('sha256', $email),
        ])->save();

        return $vendor;
    }

    private function as(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $user->email);
    }

    public function test_vendor_can_add_edit_switch_and_remove_a_wrapper(): void
    {
        $vendor = $this->vendor();

        $created = $this->as($vendor)->postJson('/api/vendor/customization-options', [
            'type' => 'wrapper',
            'name' => 'Kraft paper',
            'description' => 'Brown recycled kraft',
            'color_hex' => '#C8A27A',
            'price' => 45,
        ])->assertCreated()->json('data');

        $this->assertSame('#c8a27a', $created['color_hex']);
        $this->assertTrue((bool) $created['is_active']);

        $this->as($vendor)->postJson("/api/vendor/customization-options/{$created['id']}", [
            'description' => 'Matte kraft paper',
            'price' => 50,
        ])->assertOk()->assertJsonPath('data.description', 'Matte kraft paper');

        $this->as($vendor)->patchJson("/api/vendor/customization-options/{$created['id']}/active", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->as($vendor)->deleteJson("/api/vendor/customization-options/{$created['id']}")->assertOk();
        $this->assertDatabaseCount('bouquet_customization_options', 0);
    }

    public function test_validation_requires_a_hex_colour_and_a_filler_model(): void
    {
        $vendor = $this->vendor();

        $this->as($vendor)->postJson('/api/vendor/customization-options', [
            'type' => 'ribbon', 'name' => 'Satin', 'color_hex' => 'red',
        ])->assertStatus(422)->assertJsonValidationErrors('color_hex', 'errors');

        $this->as($vendor)->postJson('/api/vendor/customization-options', [
            'type' => 'filler', 'name' => "Baby's breath",
        ])->assertStatus(422)->assertJsonValidationErrors('model_file', 'errors');

        $this->as($vendor)->postJson('/api/vendor/customization-options', [
            'type' => 'vase', 'name' => 'Glass',
        ])->assertStatus(422)->assertJsonValidationErrors('type', 'errors');
    }

    public function test_vendors_cannot_see_or_change_each_others_items(): void
    {
        $owner = $this->vendor();
        $other = $this->vendor('other@example.com');
        $option = BouquetCustomizationOption::create([
            'owner_id' => $owner->id, 'type' => 'ribbon', 'name' => 'Satin', 'color_hex' => '#d95f76',
        ]);

        $this->as($other)->getJson('/api/vendor/customization-options')->assertOk()->assertJsonCount(0, 'data');
        $this->as($other)->postJson("/api/vendor/customization-options/{$option->id}", ['name' => 'Taken'])->assertNotFound();
        $this->as($other)->deleteJson("/api/vendor/customization-options/{$option->id}")->assertNotFound();
    }

    public function test_storefront_lists_only_switched_on_wrappers_and_fillers_with_models(): void
    {
        $vendor = $this->vendor();
        $make = fn (array $attributes) => BouquetCustomizationOption::create($attributes + ['owner_id' => $vendor->id]);
        $make(['type' => 'wrapper', 'name' => 'On', 'color_hex' => '#ffffff']);
        $make(['type' => 'wrapper', 'name' => 'Off', 'color_hex' => '#000000', 'is_active' => false]);
        $make(['type' => 'filler', 'name' => 'Eucalyptus', 'model_url' => 'https://example.com/e.glb']);
        $make(['type' => 'filler', 'name' => 'No model']);
        $make(['type' => 'ribbon', 'name' => 'Satin', 'color_hex' => '#d95f76']);

        $this->getJson("/api/stores/{$vendor->id}/customization-options")
            ->assertOk()
            ->assertJsonPath('data.wrappers.0.name', 'On')
            ->assertJsonCount(1, 'data.wrappers')
            ->assertJsonCount(1, 'data.fillers')
            ->assertJsonCount(1, 'data.ribbons');
    }

    public function test_a_bouquet_costs_its_occasions_base_price_and_wrappers_ribbons_fillers_are_included(): void
    {
        $vendor = $this->vendor();
        \App\Models\VendorApplication::query()->insert([
            'email' => $vendor->email,
            'bouquet_presets' => json_encode(['romance' => ['enabled' => true, 'sizes' => [6, 12], 'base_price' => 350]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $wrapper = BouquetCustomizationOption::create(['owner_id' => $vendor->id, 'type' => 'wrapper', 'name' => 'Kraft', 'color_hex' => '#c8a27a', 'price' => 40]);
        $ribbon = BouquetCustomizationOption::create(['owner_id' => $vendor->id, 'type' => 'ribbon', 'name' => 'Satin', 'color_hex' => '#d95f76', 'price' => 15]);
        $filler = BouquetCustomizationOption::create(['owner_id' => $vendor->id, 'type' => 'filler', 'name' => 'Eucalyptus', 'model_url' => 'https://example.com/e.glb', 'price' => 20]);

        $result = BouquetCustomizationOption::resolveSelections($vendor->id, [
            'type' => 'custom_flower_bouquet',
            'preset' => ['key' => 'romance', 'size' => 6, 'base_price' => 1],
            'paper_color' => '#000000',
            'wrapper' => ['id' => $wrapper->id, 'price' => 0],
            'ribbon' => ['id' => $ribbon->id, 'price' => 0],
            'fillers' => [
                ['id' => $filler->id, 'price' => 0, 'scene_id' => 'a', 'position' => ['x' => 1]],
                ['id' => $filler->id, 'price' => 0, 'scene_id' => 'b'],
            ],
        ]);

        $this->assertTrue($result['ok']);
        // The saved base price only: the client's price and the options' old prices don't count
        $this->assertSame(350.0, $result['total']);
        $this->assertSame(350.0, $result['customizations']['preset']['base_price']);
        $this->assertSame('#c8a27a', $result['customizations']['paper_color']);
        $this->assertSame('#d95f76', $result['customizations']['ribbon_color']);
        $this->assertSame(0.0, $result['customizations']['wrapper']['price']);
        $this->assertSame(0.0, $result['customizations']['fillers'][0]['price']);
        $this->assertSame('https://example.com/e.glb', $result['customizations']['fillers'][0]['model_3d_url']);
        $this->assertSame(['x' => 1], $result['customizations']['fillers'][0]['position']);

        $wrapper->update(['is_active' => false]);
        $this->assertFalse(BouquetCustomizationOption::resolveSelections($vendor->id, ['wrapper' => ['id' => $wrapper->id]])['ok']);

        $other = $this->vendor('other@example.com');
        $this->assertFalse(BouquetCustomizationOption::resolveSelections($other->id, ['ribbon' => ['id' => $ribbon->id]])['ok']);

        $none = BouquetCustomizationOption::resolveSelections($vendor->id, ['type' => 'custom_flower_bouquet']);
        $this->assertTrue($none['ok']);
        $this->assertSame(0.0, $none['total']);
    }

    public function test_a_wrapper_can_carry_a_print_that_reaches_the_bouquet(): void
    {
        $vendor = $this->vendor();

        $love = $this->as($vendor)->postJson('/api/vendor/customization-options', [
            'type' => 'wrapper', 'name' => 'Love print', 'color_hex' => '#D6336C', 'pattern' => 'words', 'pattern_text' => '  love ',
        ])->assertCreated()->json('data');
        $this->assertSame('words', $love['pattern']);
        $this->assertSame('love', $love['pattern_text']);

        $this->as($vendor)->postJson('/api/vendor/customization-options', [
            'type' => 'wrapper', 'name' => 'No word', 'color_hex' => '#ffffff', 'pattern' => 'words',
        ])->assertStatus(422)->assertJsonValidationErrors('pattern_text', 'errors');

        $news = $this->as($vendor)->postJson('/api/vendor/customization-options', [
            'type' => 'wrapper', 'name' => 'Newspaper', 'color_hex' => '#efece4', 'pattern' => 'newspaper', 'pattern_text' => 'ignored',
        ])->assertCreated()->json('data');
        $this->assertNull($news['pattern_text']);

        // Back to plain
        $this->as($vendor)->postJson("/api/vendor/customization-options/{$love['id']}", ['pattern' => 'none'])
            ->assertOk()->assertJsonPath('data.pattern', null);
        $this->as($vendor)->postJson("/api/vendor/customization-options/{$love['id']}", ['pattern' => 'words', 'pattern_text' => 'love'])->assertOk();

        // The bouquet gets the print from the wrapper, whatever the browser sent
        $result = BouquetCustomizationOption::resolveSelections($vendor->id, [
            'type' => 'custom_flower_bouquet',
            'wrapper' => ['id' => $love['id']],
            'wrapper_pattern' => ['type' => 'newspaper'],
        ]);
        $this->assertSame(['type' => 'words', 'text' => 'love'], $result['customizations']['wrapper_pattern']);

        $plain = BouquetCustomizationOption::create(['owner_id' => $vendor->id, 'type' => 'wrapper', 'name' => 'Kraft', 'color_hex' => '#c8a27a']);
        $result = BouquetCustomizationOption::resolveSelections($vendor->id, [
            'type' => 'custom_flower_bouquet', 'wrapper' => ['id' => $plain->id], 'wrapper_pattern' => ['type' => 'newspaper'],
        ]);
        $this->assertArrayNotHasKey('wrapper_pattern', $result['customizations']);

        // A printed wrapper picked for the inner layer keeps its print there
        $result = BouquetCustomizationOption::resolveSelections($vendor->id, [
            'type' => 'custom_flower_bouquet',
            'wrapper' => ['id' => $plain->id],
            'inner_wrapper' => ['id' => $love['id']],
            'inner_wrapper_pattern' => ['type' => 'newspaper'],
        ]);
        $this->assertArrayNotHasKey('wrapper_pattern', $result['customizations']);
        $this->assertSame(['type' => 'words', 'text' => 'love'], $result['customizations']['inner_wrapper_pattern']);

        // The storefront sends the print with the wrapper
        $this->getJson("/api/stores/{$vendor->id}/customization-options")->assertOk();
    }
}
