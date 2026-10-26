<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_mobile_numbers_in_any_common_shape_become_09xx_xxx_xxxx(): void
    {
        foreach (['09171234567', '0917 123 4567', '0917-123-4567', '+63 917 123 4567', '639171234567', '9171234567'] as $typed) {
            $this->assertSame('0917 123 4567', Phone::format($typed), $typed);
        }
    }

    public function test_anything_that_is_not_a_mobile_number_is_rejected(): void
    {
        foreach (['', '12345', '0917123456', '091712345678', '(02) 8123 4567', '08171234567', 'call me'] as $typed) {
            $this->assertNull(Phone::format($typed), $typed);
        }
    }
}
