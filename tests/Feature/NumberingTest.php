<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_numbers_are_sequential_per_country_and_type(): void
    {
        $this->seedReference();
        $n = app(NumberingService::class);
        $ug = Country::where('iso2', 'UG')->first();
        $ng = Country::where('iso2', 'NG')->first();
        $y = now()->format('Y');

        $this->assertSame("DOPAY-UG-INV-{$y}-000001", $n->document($ug, 'INV'));
        $this->assertSame("DOPAY-UG-INV-{$y}-000002", $n->document($ug, 'INV'));
        $this->assertSame("DOPAY-NG-INV-{$y}-000001", $n->document($ng, 'INV'));
        $this->assertSame("DOPAY-UG-PAY-{$y}-000001", $n->document($ug, 'PAY'));
    }
}
