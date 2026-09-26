<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Services\ApprovalService;
use App\Services\FinanceFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApprovalChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_memo_follows_the_cfo_route_with_country_and_role_checks(): void
    {
        $this->seedReference();
        $preparer = $this->makeUser('Accounting Officer', 'HQ');
        $ugAccountant = $this->makeUser('Accountant', 'HQ');
        $ngAccountant = $this->makeUser('Accountant', 'IK');
        $cfo = $this->makeUser('CFO', 'HQ');

        $form = app(FinanceFormService::class)->save('A', [
            'branch_id' => Branch::where('code', 'HQ')->value('id'),
            'data' => ['date' => now()->toDateString(), 'route' => 'CFO', 'subject' => 'Courier of samples'],
            'lines' => [['description' => 'Courier to Lagos', 'category' => ExpenseCategory::where('name', 'Cargo & Freight')->value('id'), 'amount' => 250000]],
        ], $preparer);

        $this->assertMatchesRegularExpression('/^UGD\d{4}-A-001$/', $form->reference);
        $this->assertEquals(250000, (float) $form->total);

        $approvals = app(ApprovalService::class);
        $approvals->submit($form, $preparer);
        $form->refresh();

        $this->assertSame('in_approval', $form->status);
        $this->assertSame(0, $approvals->currentStep($form));
        $this->assertTrue($approvals->canSign($ugAccountant, $form, 0), 'A Ugandan accountant acknowledges step 1');
        $this->assertFalse($approvals->canSign($ngAccountant, $form, 0), 'Local roles cannot sign for another country');
        $this->assertFalse($approvals->canSign($cfo, $form, 0), 'The CFO signs only the final step');
        $this->assertTrue($approvals->canSign($cfo, $form, 3));

        try {
            $approvals->sign($form, $ugAccountant, '0000');
            $this->fail('A wrong PIN must be rejected');
        } catch (ValidationException) {
        }
        $approvals->sign($form, $ugAccountant, '2468');
        $this->assertSame(1, $approvals->currentStep($form->refresh()));
    }
}
