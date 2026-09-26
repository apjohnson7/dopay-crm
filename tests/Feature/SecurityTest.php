<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function ugInvoice()
    {
        $ug = Country::where('iso2', 'UG')->first();
        $customer = Customer::create(['code' => 'C-UG-0001', 'name' => 'Buyer', 'country_id' => $ug->id, 'branch_id' => Branch::where('code', 'HQ')->value('id'), 'currency_code' => 'UGX']);

        return app(InvoiceService::class)->saveDraft(['customer_id' => $customer->id, 'issue_date' => now()->toDateString(), 'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 1000]]], $this->makeUser('Accounting Officer'));
    }

    public function test_staff_cannot_act_on_another_countrys_invoice(): void
    {
        $this->seedReference();
        $invoice = $this->ugInvoice();
        $ng = $this->makeUser('Accountant', 'IK');

        $this->actingAs($ng)->post(route('invoices.submit', $invoice))->assertForbidden();
        $this->actingAs($ng)->post(route('invoices.generate', $invoice))->assertForbidden();
        $this->actingAs($ng)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_preparer_cannot_approve_own_invoice(): void
    {
        $this->seedReference();
        $invoice = $this->ugInvoice();
        $manager = $this->makeUser('Finance Manager');
        $invoice->update(['status' => 'pending_approval', 'created_by' => $manager->id]);

        $this->actingAs($manager)->post(route('invoices.approve', $invoice))->assertForbidden();
        $this->assertSame('pending_approval', $invoice->fresh()->status);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_api_token_cannot_read_other_countries(): void
    {
        $this->seedReference();
        $invoice = $this->ugInvoice();
        $ng = $this->makeUser('Accountant', 'IK');
        $token = $ng->createToken('test', ['read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/invoices/'.$invoice->id)->assertNotFound();

        $noAbility = $ng->createToken('bad', ['write'])->plainTextToken;
        $this->withToken($noAbility)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_a_preparer_cannot_approve_their_own_finance_form(): void
    {
        $this->seedReference();
        $accountant = $this->makeUser('Accountant');
        $form = app(\App\Services\FinanceFormService::class)->save('A', [
            'branch_id' => Branch::where('code', 'HQ')->value('id'),
            'data' => ['date' => now()->toDateString(), 'route' => 'CFO', 'subject' => 'Office chairs'],
            'lines' => [['description' => 'Chairs', 'category' => \App\Models\ExpenseCategory::where('name', 'Office Expenses')->value('id'), 'amount' => 900000]],
        ], $accountant);
        $approvals = app(\App\Services\ApprovalService::class);
        $approvals->submit($form, $accountant);

        $this->assertFalse($approvals->canSign($accountant, $form->refresh(), 0), 'The preparer must not acknowledge their own memo');

        $cm = $this->makeUser('Country Manager');
        $approvals->sign($form, $cm, '2468');
        $this->assertFalse($approvals->canSign($cm, $form->refresh(), 1), 'One person signs one approval step');
    }

    public function test_negative_form_lines_are_rejected(): void
    {
        $this->seedReference();
        $this->actingAs($this->makeUser('Accountant'))->post(route('forms.store', 'A'), [
            'branch_id' => Branch::where('code', 'HQ')->value('id'),
            'data' => ['date' => now()->toDateString(), 'route' => 'CFO', 'subject' => 'Test'],
            'lines' => [['description' => 'Refund', 'amount' => -5000]],
        ])->assertSessionHasErrors('lines.0.amount');
    }

    public function test_audit_trail_is_limited_to_the_viewers_country(): void
    {
        $this->seedReference();
        \App\Services\AuditLogger::log('Uganda-only event', Country::where('iso2', 'UG')->first(), null, null, 'UG secret');
        $this->actingAs($this->makeUser('Finance Manager', 'IK'))->get(route('audit.index'))->assertOk()->assertDontSee('UG secret');
        $this->actingAs($this->makeUser('Finance Manager', 'HQ'))->get(route('audit.index'))->assertOk()->assertSee('UG secret');
    }
}
