<?php

namespace Tests\Unit;

use App\Support\ProductCode;
use PHPUnit\Framework\TestCase;

class ProductCodeTest extends TestCase
{
    public function test_codes_match_the_frontend_and_round_trip(): void
    {
        // The same codes frontend/src/utils/productLink.js makes
        $this->assertSame('2oSZSU7', ProductCode::encode(1));
        $this->assertSame('3Jq0bm3', ProductCode::encode(2));
        $this->assertSame('45Gf4lP', ProductCode::encode(108));

        foreach ([1, 2, 99, 108, 65535, 65536, 123456, 2147483648, 4294967295] as $id) {
            $this->assertSame($id, ProductCode::decode(ProductCode::encode($id)));
        }
    }

    public function test_plain_ids_and_bad_codes_are_refused(): void
    {
        foreach (['1', '108', '0000001', 'zzzzzzz', '2oSZSU8', '', '2oSZSU7x'] as $value) {
            $this->assertNull(ProductCode::decode($value), $value);
        }
    }
}
