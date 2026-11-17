<?php

namespace Tests\Unit;

use App\Models\VendorApplication;
use Carbon\Carbon;
use Tests\TestCase;

/** Days outside a shop's opening hours (e.g. Sunday for a Mon to Sat shop) can't be picked for delivery. */
class ShopOpenDaysTest extends TestCase
{
    private function day(string $date): Carbon
    {
        return Carbon::parse($date, VendorApplication::RESERVATION_TIMEZONE);
    }

    public function test_a_day_outside_the_opening_hours_is_closed(): void
    {
        $shop = (new VendorApplication())->forceFill([
            'operating_schedules' => [[
                'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'opening_time' => '08:00', 'closing_time' => '18:00',
            ]],
        ]);

        $this->assertFalse($shop->isOpenOn($this->day('2026-11-29')), 'Sunday');
        $this->assertTrue($shop->isOpenOn($this->day('2026-11-28')), 'Saturday');
        $this->assertTrue($shop->isOpenOn($this->day('2026-11-30')), 'Monday');
    }

    public function test_a_shop_without_opening_hours_is_open_every_day(): void
    {
        $shop = (new VendorApplication())->forceFill(['operating_schedules' => []]);

        $this->assertTrue($shop->isOpenOn($this->day('2026-11-29')));
    }
}
