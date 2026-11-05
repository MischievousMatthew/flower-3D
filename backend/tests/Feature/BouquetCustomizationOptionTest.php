<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSubscriptionModuleAccess;
use App\Models\BouquetCustomizationOption;
use App\Models\User;
use App\Support\BouquetPresets;
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
        (include database_path('migrations/2026_11_04_150000_add_pattern_size_to_bouquet_customization_options.php'))->up();

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

        // The shop can make the word bigger or smaller (kept between 50% and 200%)
        $this->as($vendor)->postJson("/api/vendor/customization-options/{$love['id']}", ['pattern' => 'words', 'pattern_text' => 'love', 'pattern_size' => 150])
            ->assertOk()->assertJsonPath('data.pattern_size', 150);
        $this->as($vendor)->postJson("/api/vendor/customization-options/{$love['id']}", ['pattern' => 'words', 'pattern_text' => 'love', 'pattern_size' => 500])
            ->assertStatus(422);
        $result = BouquetCustomizationOption::resolveSelections($vendor->id, [
            'type' => 'custom_flower_bouquet', 'wrapper' => ['id' => $love['id']],
        ]);
        $this->assertSame(['type' => 'words', 'text' => 'love', 'size' => 150], $result['customizations']['wrapper_pattern']);

        // The storefront sends the print with the wrapper
        $this->getJson("/api/stores/{$vendor->id}/customization-options")->assertOk();
    }

    public function test_the_filler_limit_counts_different_fillers_not_copies(): void
    {
        $vendor = $this->vendor();
        $make = fn ($name) => BouquetCustomizationOption::create(['owner_id' => $vendor->id, 'type' => 'filler', 'name' => $name, 'model_url' => 'https://example.com/f.glb']);
        $red = $make('Red');
        $others = collect(range(1, 5))->map(fn ($n) => $make("Filler {$n}"));
        $limit = BouquetPresets::MIN_FILLERS; // a legacy bouquet's limit

        // The same filler many times counts once
        $copies = array_fill(0, 8, ['id' => $red->id]);
        $this->assertTrue(BouquetCustomizationOption::resolveSelections($vendor->id, ['type' => 'custom_flower_bouquet', 'fillers' => $copies])['ok']);

        // One different filler past the limit is refused
        $different = $others->take($limit)->map(fn ($o) => ['id' => $o->id])->push(['id' => $red->id])->all();
        $result = BouquetCustomizationOption::resolveSelections($vendor->id, ['type' => 'custom_flower_bouquet', 'fillers' => $different]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('different fillers', $result['message']);
    }

    public function test_filler_pieces_moved_on_their_own_keep_their_pose(): void
    {
        $vendor = $this->vendor();
        $filler = BouquetCustomizationOption::create(['owner_id' => $vendor->id, 'type' => 'filler', 'name' => 'Eucalyptus', 'model_url' => 'https://example.com/e.glb']);

        $result = BouquetCustomizationOption::resolveSelections($vendor->id, [
            'type' => 'custom_flower_bouquet',
            'fillers' => [[
                'id' => $filler->id,
                'arranged' => true,
                'sprigs' => [
                    ['i' => 1, 'a' => 0.2, 't' => [0.1, 0.2, 0.3, 0, 0, 0, 1, 0.5]],
                    ['i' => 2, 't' => [1, 2, 3]], // not a pose: dropped
                    ['i' => 3, 't' => [0, 0, 0, 0, 0, 0, 1, 0]], // no size: dropped
                ],
            ]],
        ]);

        $saved = $result['customizations']['fillers'][0];
        $this->assertTrue($saved['arranged']);
        $this->assertSame([0.1, 0.2, 0.3, 0.0, 0.0, 0.0, 1.0, 0.5], $saved['sprigs'][0]['t']);
        $this->assertArrayNotHasKey('t', $saved['sprigs'][1]);
        $this->assertArrayNotHasKey('t', $saved['sprigs'][2]);
    }

    public function test_group_ids_on_bouquet_items_are_kept_only_when_safe(): void
    {
        $this->assertSame('gm3k2abcd', BouquetCustomizationOption::cleanGroup('gm3k2abcd'));
        $this->assertNull(BouquetCustomizationOption::cleanGroup('<script>'));
        $this->assertNull(BouquetCustomizationOption::cleanGroup(123));
        $this->assertNull(BouquetCustomizationOption::cleanGroup(str_repeat('a', 41)));
    }

    public function test_a_3d_wrapper_needs_a_model_file_of_up_to_10_mb(): void
    {
        $vendor = $this->vendor();
        $this->as($vendor)->postJson('/api/vendor/customization-options', ['type' => 'wrap_model', 'name' => 'My cone'])
            ->assertStatus(422)->assertJsonValidationErrors('model_file', 'errors');
        $this->as($vendor)->post('/api/vendor/customization-options', [
            'type' => 'wrap_model', 'name' => 'My cone',
            'model_file' => \Illuminate\Http\UploadedFile::fake()->create('cone.glb', 11000),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('model_file', 'errors');
    }

    public function test_an_occasion_on_a_deleted_3d_wrapper_goes_back_to_its_usual_style(): void
    {
        $vendor = $this->vendor();
        $model = BouquetCustomizationOption::create(['owner_id' => $vendor->id, 'type' => 'wrap_model', 'name' => 'My cone', 'model_url' => 'https://example.com/cone.glb']);
        \App\Models\VendorApplication::query()->insert([
            'email' => $vendor->email, 'status' => 'approved',
            'bouquet_presets' => json_encode(['graduation' => ['design' => "model_{$model->id}"]]),
        ]);

        $graduation = collect(\App\Support\BouquetPresets::forOwner($vendor->id))->firstWhere('key', 'graduation');
        $this->assertSame("model_{$model->id}", $graduation['design']);
        $this->assertSame('https://example.com/cone.glb', $graduation['model_url']);
        $this->assertSame('My cone', $graduation['model_name']);

        $model->delete();
        $graduation = collect(\App\Support\BouquetPresets::forOwner($vendor->id))->firstWhere('key', 'graduation');
        $this->assertSame('classic', $graduation['design']);
        $this->assertArrayNotHasKey('model_url', $graduation);
    }
}
