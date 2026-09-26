<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Services\InvoiceService;
use App\Services\TaxService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_vat_charged_on_invoices_is_matched_to_tax_payments(): void
    {
        $this->seedReference();
        Carbon::setTestNow('2026-09-26 10:00:00');
        $officer = $this->makeUser('Accounting Officer');
        $manager = $this->makeUser('Finance Manager');
        $ug = Country::where('iso2', 'UG')->first();
        $hq = Branch::where('code', 'HQ')->first();
        $customer = Customer::create(['code' => 'C-UG-9001', 'name' => 'Test Buyer', 'country_id' => $ug->id, 'branch_id' => $hq->id, 'currency_code' => 'UGX', 'tax_id' => '1000000001']);

        $invoices = app(InvoiceService::class);
        $inv = $invoices->saveDraft(['customer_id' => $customer->id, 'issue_date' => '2026-08-10', 'items' => [['description' => 'Training', 'quantity' => 1, 'unit_price' => 1000000]]], $officer);
        $invoices->submit($inv);
        $invoices->approve($inv->fresh(), $manager);
        // A draft is not a taxable supply yet
        $invoices->saveDraft(['customer_id' => $customer->id, 'issue_date' => '2026-08-12', 'items' => [['description' => 'Draft only', 'quantity' => 1, 'unit_price' => 500000]]], $officer);

        $tax = app(TaxService::class);
        $aug = $tax->periods($ug)->firstWhere('period', '2026-08');
        $this->assertEquals(180000, $aug['vat']);
        $this->assertSame('Overdue', $aug['status'], 'The August return was due on 15 September');

        $payment = $tax->record($ug, $hq, ['description' => 'VAT return, August 2026', 'amount' => 180000, 'spent_on' => '2026-09-26', 'tax_period' => '2026-08'], $officer);
        $this->assertSame('In approval', $tax->periods($ug)->firstWhere('period', '2026-08')['status']);

        try {
            $tax->markPaid($payment, $officer);
            $this->fail('The person who recorded a tax payment cannot approve it');
        } catch (HttpException) {
        }
        $tax->markPaid($payment->fresh(), $manager);

        $aug = $tax->periods($ug)->firstWhere('period', '2026-08');
        $this->assertEquals(180000, $aug['paid']);
        $this->assertEquals(0, $aug['balance']);
        $this->assertSame('Paid', $aug['status']);
    }
}
