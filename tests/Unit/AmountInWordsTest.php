<?php

namespace Tests\Unit;

use App\Services\AmountInWords;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_whole_numbers(): void
    {
        $this->assertSame('zero', AmountInWords::number(0));
        $this->assertSame('one hundred and one', AmountInWords::number(101));
        $this->assertSame('One million two hundred and fifty thousand', ucfirst(AmountInWords::number(1250000)));
    }

    public function test_currency_amounts_used_on_forms(): void
    {
        $this->assertSame('One million two hundred and fifty thousand Uganda shillings only', AmountInWords::amount(1250000, 'UGX'));
        $this->assertSame('One thousand two hundred and thirty-four naira and fifty kobo only', AmountInWords::amount(1234.5, 'NGN'));
    }
}
