<?php

namespace Tests\Feature;

use App\Services\Pricing;
use Tests\TestCase;

class StorefrontPolishTest extends TestCase
{
    public function test_price_display_groups_digits_without_changing_editable_values(): void
    {
        $this->assertSame('PKR 15,000', Pricing::format(1500000));
        $this->assertSame('USD 1,234.56', Pricing::format(123456, 'USD'));
        $this->assertSame('PKR -1,234.56', Pricing::format(-123456));
        $this->assertSame('15000.00', Pricing::decimal(1500000));
        $this->assertSame('Price not configured', Pricing::format(null));
    }
}
