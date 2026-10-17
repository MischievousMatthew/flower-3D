<?php

namespace Tests\Unit;

use App\Support\BouquetPresets;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BouquetPresetsTest extends TestCase
{
    private function bouquet(array $preset = null, int $flowers = 1, array $extra = []): array
    {
        return array_merge([
            'type' => 'custom_flower_bouquet',
            'preset' => $preset,
            'flowers' => array_fill(0, $flowers, ['id' => 1]),
        ], $extra);
    }

    public function test_defaults_turn_every_occasion_on_with_its_sizes(): void
    {
        $presets = BouquetPresets::merge(null);

        $this->assertSame(['wedding', 'romance', 'birthday', 'graduation', 'sympathy', 'just_because'], array_column($presets, 'key'));
        $wedding = $presets[0];
        $this->assertTrue($wedding['enabled']);
        $this->assertSame([6, 12, 24], $wedding['sizes']);
        $this->assertFalse($wedding['wrapper']);
        $this->assertTrue($wedding['ribbon']);
    }

    public function test_saved_settings_override_defaults_and_unknown_keys_are_ignored(): void
    {
        $presets = collect(BouquetPresets::merge([
            'birthday' => ['enabled' => false, 'sizes' => [5, 10]],
            'made_up' => ['enabled' => true, 'sizes' => [2]],
        ]))->keyBy('key');

        $this->assertFalse($presets['birthday']['enabled']);
        $this->assertSame([5, 10], $presets['birthday']['sizes']);
        $this->assertFalse($presets->has('made_up'));
    }

    public function test_normalize_sorts_sizes_and_keeps_known_occasions(): void
    {
        $saved = BouquetPresets::normalize([
            'wedding' => ['enabled' => true, 'sizes' => [24, 6, 12]],
            'sympathy' => ['enabled' => false, 'sizes' => [12]],
        ]);

        $this->assertSame([6, 12, 24], $saved['wedding']['sizes']);
        $this->assertFalse($saved['sympathy']['enabled']);
    }

    /** @dataProvider badInputs */
    public function test_normalize_rejects_bad_sizes(array $input, string $field): void
    {
        try {
            BouquetPresets::normalize($input);
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public static function badInputs(): array
    {
        return [
            'too big' => [['wedding' => ['enabled' => true, 'sizes' => [37]]], 'wedding.sizes'],
            'zero' => [['wedding' => ['enabled' => true, 'sizes' => [0]]], 'wedding.sizes'],
            'duplicate' => [['wedding' => ['enabled' => true, 'sizes' => [6, 6]]], 'wedding.sizes'],
            'too many' => [['wedding' => ['enabled' => true, 'sizes' => [1, 2, 3, 4, 5]]], 'wedding.sizes'],
            'none' => [['wedding' => ['enabled' => true, 'sizes' => []]], 'wedding.sizes'],
        ];
    }

    public function test_at_least_one_occasion_must_stay_on(): void
    {
        $allOff = array_fill_keys(BouquetPresets::KEYS, ['enabled' => false, 'sizes' => [3]]);

        $this->expectException(ValidationException::class);
        BouquetPresets::normalize($allOff);
    }

    public function test_check_accepts_a_bouquet_within_its_size(): void
    {
        $result = BouquetPresets::check(BouquetPresets::merge(null), $this->bouquet(['key' => 'romance', 'size' => 12], 12));

        $this->assertTrue($result['ok']);
        $this->assertSame(6, $result['filler_limit']);
    }

    public function test_check_rejects_too_many_flowers_a_size_not_offered_and_a_disabled_occasion(): void
    {
        $presets = BouquetPresets::merge(['birthday' => ['enabled' => false, 'sizes' => [4]]]);

        $this->assertFalse(BouquetPresets::check($presets, $this->bouquet(['key' => 'romance', 'size' => 12], 13))['ok']);
        $this->assertFalse(BouquetPresets::check($presets, $this->bouquet(['key' => 'romance', 'size' => 7], 3))['ok']);
        $disabled = BouquetPresets::check($presets, $this->bouquet(['key' => 'birthday', 'size' => 4], 2));
        $this->assertFalse($disabled['ok']);
        $this->assertStringContainsString('Birthday', $disabled['message']);
    }

    public function test_wedding_drops_wrappers_and_sympathy_drops_the_ribbon(): void
    {
        $presets = BouquetPresets::merge(null);
        $extras = ['wrapper' => ['id' => 5], 'inner_wrapper' => ['id' => 6], 'ribbon' => ['id' => 7]];

        $wedding = BouquetPresets::check($presets, $this->bouquet(['key' => 'wedding', 'size' => 6], 3, $extras));
        $this->assertArrayNotHasKey('wrapper', $wedding['customizations']);
        $this->assertArrayNotHasKey('inner_wrapper', $wedding['customizations']);
        $this->assertSame(['id' => 7], $wedding['customizations']['ribbon']);

        $sympathy = BouquetPresets::check($presets, $this->bouquet(['key' => 'sympathy', 'size' => 6], 3, $extras));
        $this->assertArrayNotHasKey('ribbon', $sympathy['customizations']);
        $this->assertSame(['id' => 5], $sympathy['customizations']['wrapper']);
    }

    public function test_bouquets_from_before_presets_keep_the_three_flower_limit(): void
    {
        $presets = BouquetPresets::merge(null);

        $this->assertTrue(BouquetPresets::check($presets, $this->bouquet(null, 3))['ok']);
        $this->assertFalse(BouquetPresets::check($presets, $this->bouquet(null, 4))['ok']);
        $this->assertSame(5, BouquetPresets::check($presets, $this->bouquet(null, 2))['filler_limit']);
    }

    public function test_largest_size_is_the_checkout_cap(): void
    {
        $this->assertSame(24, BouquetPresets::largestSize(BouquetPresets::merge(null)));
        $this->assertSame(36, BouquetPresets::MAX_SIZE);
    }
}
