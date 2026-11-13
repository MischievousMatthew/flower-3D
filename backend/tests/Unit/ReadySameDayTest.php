<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\VendorApplication;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * A product is either "ready the same day" (deliverable today whatever the
 * shop's same-day switch says) or follows the shop's preparation time.
 */
class ReadySameDayTest extends TestCase
{
    private function shop(bool $sameDay, string $leadTime = '2 days'): VendorApplication
    {
        return (new VendorApplication())->forceFill([
            'same_day_delivery' => $sameDay,
            'lead_time' => $leadTime,
            'max_orders_per_day' => 10,
            // Open every day until 6 PM
            'operating_schedules' => [[
                'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'opening_time' => '08:00', 'closing_time' => '18:00',
            ]],
        ]);
    }

    private function product(bool $readySameDay): Product
    {
        return (new Product())->forceFill(['ready_same_day' => $readySameDay]);
    }

    public function test_an_order_is_ready_the_same_day_only_when_every_product_is(): void
    {
        $this->assertTrue(Product::allReadySameDay([$this->product(true), $this->product(true)]));
        $this->assertFalse(Product::allReadySameDay([$this->product(true), $this->product(false)]));
        $this->assertFalse(Product::allReadySameDay([]), 'no products: the shop decides');
    }

    public function test_ready_the_same_day_products_open_today_even_with_the_shops_switch_off(): void
    {
        $shop = $this->shop(false);
        $morning = Carbon::parse('2026-11-16 10:00', VendorApplication::RESERVATION_TIMEZONE);

        // The shop's own rules: no same-day
        $own = VendorApplication::buildReservationSettings(null, $shop, $morning);
        $this->assertFalse($own['same_day_delivery']);
        $this->assertFalse($own['same_day_available_today']);

        // Every product ready the same day: today is open, until the shop closes
        $ready = VendorApplication::buildReservationSettings(null, $shop, $morning, true);
        $this->assertTrue($ready['same_day_delivery']);
        $this->assertTrue($ready['same_day_from_products']);
        $this->assertTrue($ready['same_day_available_today']);
        $this->assertSame('18:00', $ready['cutoff_time_today']);
        $this->assertSame(2, $ready['lead_time_days'], "the shop's preparation time is unchanged");

        // After closing time it's too late for today
        $evening = Carbon::parse('2026-11-16 19:30', VendorApplication::RESERVATION_TIMEZONE);
        $late = VendorApplication::buildReservationSettings(null, $shop, $evening, true);
        $this->assertTrue($late['same_day_cutoff_reached']);
        $this->assertFalse($late['same_day_available_today']);
    }

    public function test_a_shop_that_already_delivers_the_same_day_is_unchanged(): void
    {
        $morning = Carbon::parse('2026-11-16 10:00', VendorApplication::RESERVATION_TIMEZONE);
        $settings = VendorApplication::buildReservationSettings(null, $this->shop(true), $morning, true);

        $this->assertTrue($settings['same_day_available_today']);
        $this->assertFalse($settings['same_day_from_products']);
    }

    public function test_a_products_wait_is_none_when_ready_the_same_day_else_the_shops_preparation_time(): void
    {
        $shop = $this->shop(false, '3 days');

        $this->assertSame(0, $this->product(true)->prepDaysFor($shop));
        $this->assertSame(3, $this->product(false)->prepDaysFor($shop));
        $this->assertSame(0, $this->product(false)->prepDaysFor(null));
    }
}
