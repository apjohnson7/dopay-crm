<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Services\Accounting\FinancialStatements;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\PeriodCloseService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private function sale(string $date = '2026-08-10'): array
    {
        $ug = Country::where('iso2', 'UG')->first();
        $customer = Customer::create(['code' => 'C-UG-7001', 'name' => 'Ledger Buyer', 'country_id' => $ug->id, 'branch_id' => Branch::where('code', 'HQ')->value('id'), 'currency_code' => 'UGX']);
        $invoices = app(InvoiceService::class);
        $officer = $this->makeUser('Accounting Officer');
        $inv = $invoices->saveDraft(['customer_id' => $customer->id, 'issue_date' => $date, 'items' => [['description' => 'Fertiliser', 'quantity' => 10, 'unit_price' => 100000]]], $officer);
        $invoices->submit($inv);
        $invoices->approve($inv->fresh(), $this->makeUser('Finance Manager'));

        return [$ug, $customer, $inv->fresh(), $officer];
    }

    public function test_invoice_and_payment_post_balanced_double_entries(): void
    {
        $this->seedReference();
        [$ug, $customer, $inv, $officer] = $this->sale();

        $entry = JournalEntry::with('lines.account')->where('source_key', 'invoice:'.$inv->id)->firstOrFail();
        $this->assertEquals(1180000, $entry->lines->sum('debit'));
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));
        $this->assertEquals(180000, $entry->lines->firstWhere('account.role', 'vat')->credit);

        app(PaymentService::class)->record($customer, ['amount' => 500000, 'method' => 'Mobile money', 'reference' => 'MTN-1', 'paid_on' => '2026-08-20'], [$inv->id => 500000], $officer);

        $tb = app(FinancialStatements::class)->trialBalance($ug, '2026-08-31');
        $this->assertEqualsWithDelta($tb->sum('debit'), $tb->sum('credit'), 0.001);
        $bs = app(FinancialStatements::class)->balanceSheet($ug, '2026-08-31');
        $this->assertEqualsWithDelta($bs['total_assets'], $bs['total_liabilities'] + $bs['total_equity'], 0.001);
        $this->assertEquals(1000000, app(FinancialStatements::class)->profitAndLoss($ug, '2026-01-01', '2026-08-31')['total_income']);
    }

    public function test_posting_twice_is_idempotent_and_cancel_reverses(): void
    {
        $this->seedReference();
        [$ug, , $inv] = $this->sale();
        app(\App\Services\Accounting\PostingRules::class)->invoice($inv);
        $this->assertSame(1, JournalEntry::where('source_key', 'invoice:'.$inv->id)->count());

        app(InvoiceService::class)->cancel($inv, 'Duplicate');
        $this->assertTrue(JournalEntry::where('source_key', 'invoice:'.$inv->id.':reversal')->exists());
        $this->assertEqualsWithDelta(0, app(FinancialStatements::class)->profitAndLoss($ug, '2026-01-01', now()->toDateString())['total_income'], 0.001);
    }

    public function test_closed_month_rejects_new_postings(): void
    {
        $this->seedReference();
        $ug = Country::where('iso2', 'UG')->first();
        $ug->update(['books_closed_through' => '2026-08']);

        $this->expectException(ValidationException::class);
        app(LedgerService::class)->post($ug, '2026-08-15', 'Late accrual', [['exp_office', 1000, 0], ['accrued', 0, 1000]], 'manual');
    }

    public function test_unbalanced_manual_journal_is_refused_and_close_runs_in_order(): void
    {
        $this->seedReference();
        $ug = Country::where('iso2', 'UG')->first();
        try {
            app(LedgerService::class)->post($ug, '2026-06-30', 'Wrong', [['exp_office', 1000, 0], ['accrued', 0, 900]], 'manual');
            $this->fail('Unbalanced journal accepted');
        } catch (ValidationException) {
        }
        app(LedgerService::class)->post($ug, '2026-06-30', 'Accrual', [['exp_office', 1000, 0], ['accrued', 0, 1000]], 'manual');
        $ug->update(['books_closed_through' => '2026-05']);
        $close = app(PeriodCloseService::class);
        $this->assertSame('2026-06', $close->nextToClose($ug));
        $close->close($ug, '2026-06', $this->makeUser('Finance Manager'));
        $this->assertSame('2026-06', $ug->fresh()->books_closed_through);
    }

    public function test_other_countries_cannot_open_the_ugandan_books(): void
    {
        $this->seedReference();
        $this->sale();
        $ng = $this->makeUser('Finance Manager', 'IK');
        $entry = JournalEntry::where('country_id', Country::where('iso2', 'UG')->value('id'))->firstOrFail();
        $this->actingAs($ng)->get(route('accounting.journal', $entry))->assertForbidden();
        $this->actingAs($ng)->get(route('accounting.statements', ['country' => Country::where('iso2', 'UG')->value('id')]))->assertOk()->assertSee('Nigeria');
    }
}
