<?php

namespace Tests\Unit;

use App\Services\Accounting\ReconciliationService as R;
use PHPUnit\Framework\TestCase;

class StatementParsingTest extends TestCase
{
    public function test_amounts_as_banks_print_them(): void
    {
        $this->assertSame(1250000.0, R::parseAmount('1,250,000'));
        $this->assertSame(-47500.0, R::parseAmount('-47,500'));
        $this->assertSame(-1000.0, R::parseAmount('(1,000.00)'));
        $this->assertSame(50000.0, R::parseAmount('UGX 50,000'));
        $this->assertSame(1250000.5, R::parseAmount('1 250 000,50'));
        $this->assertNull(R::parseAmount('abc'));
    }

    public function test_dates_are_read_day_first(): void
    {
        $this->assertSame('2026-04-03', R::parseDate('03/04/2026'));
        $this->assertSame('2026-09-26', R::parseDate('2026-09-26'));
        $this->assertSame('2026-09-26', R::parseDate('26 Sep 2026'));
        $this->assertNull(R::parseDate('9/26/2026'));
    }
}
