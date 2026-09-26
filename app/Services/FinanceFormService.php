<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinanceForm;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceFormService
{
    public function __construct(private NumberingService $numbers, private ExchangeRateService $rates) {}

    /** Create or update a draft from the editor. */
    public function save(string $type, array $input, User $user, ?FinanceForm $form = null): FinanceForm
    {
        $def = config('dopay.forms.'.$type) ?? abort(404);

        return DB::transaction(function () use ($type, $def, $input, $user, $form) {
            $branch = Branch::with('country')->findOrFail($form?->branch_id ?? $input['branch_id']);
            if (! $user->canActForCountry($branch->country_id)) {
                abort(403, 'You can only prepare forms for your own country.');
            }
            if ($form && ! in_array($form->status, ['draft', 'returned'], true)) {
                throw ValidationException::withMessages(['status' => 'Signed forms can only be changed through an authorized revision.']);
            }
            $data = [];
            foreach ($def['fields'] as $f) {
                $v = $input['data'][$f['key']] ?? null;
                // Typed fields must point at real, allowed values (people in this country, active countries, listed options, sane amounts).
                if ($v !== null && $v !== '') {
                    $ok = match ($f['type']) {
                        'user' => \App\Models\User::whereKey((int) $v)->where('is_active', true)->whereHas('branch', fn ($b) => $b->where('country_id', $branch->country_id))->exists(),
                        'country' => \App\Models\Country::whereKey((int) $v)->where('is_active', true)->exists(),
                        'select' => array_key_exists((string) $v, $f['options'] ?? []),
                        'money', 'number' => is_numeric($v) && ((float) $v >= 0 || in_array($f['key'], ['opening_balance', 'bank_closing'], true)),
                        'date' => strtotime((string) $v) !== false,
                        default => is_scalar($v) && mb_strlen((string) $v) <= 2000,
                    };
                    if (! $ok) {
                        throw ValidationException::withMessages(['data.'.$f['key'] => 'Check “'.$f['label'].'”: that value isn’t allowed here.']);
                    }
                }
                $data[$f['key']] = $v;
            }
            if ($type === 'K' && empty($data['liquidation_date']) && ! empty($data['use_date'])) {
                $data['liquidation_date'] = $this->addWorkingDays($data['use_date'], $def['liquidation_working_days'])->toDateString();
            }
            if ($type === 'J') {
                $data['receiving_country_id'] = $branch->country_id;
            }
            $currency = $input['currency_code'] ?? $branch->country->currency_code;

            $form ??= new FinanceForm(['type' => $type, 'prepared_by' => $user->id, 'status' => 'draft', 'version' => 1]);
            $form->fill([
                'country_id' => $branch->country_id,
                'branch_id' => $branch->id,
                'currency_code' => $currency,
                'exchange_rate' => $this->rates->rate($currency),
                'data' => $data,
            ])->save();
            $form->setRelation('country', $branch->country)->setRelation('branch', $branch);
            if (! $form->reference) {
                $form->update(['reference' => $this->numbers->formReference($form)]);
            }

            if ($def['lines'] === 'budget') {
                $this->saveBudget($form, $input['budget'] ?? [], $input['budget_notes'] ?? []);
            } else {
                $this->saveLines($form, $input['lines'] ?? []);
            }
            $form->update(['total' => $this->total($form)]);

            return $form->refresh();
        });
    }

    public function validateForSubmit(FinanceForm $form): void
    {
        $def = $form->definition();
        $errors = [];
        foreach ($def['fields'] as $f) {
            if (! empty($f['required']) && blank($form->datum($f['key']))) {
                $errors['data.'.$f['key']] = $f['label'].' is required.';
            }
        }
        if (is_array($def['lines']) && ! $form->lines()->where(fn ($q) => $q->where('amount', '>', 0)->orWhere('inflow', '>', 0)->orWhere('outflow', '>', 0))->exists()) {
            $errors['lines'] = 'Add at least one line with an amount.';
        }
        if ($def['lines'] === 'budget' && (float) $form->total <= 0) {
            $errors['budget'] = 'Enter at least one budget amount.';
        }
        if ($form->type === 'G' && ($diff = $this->ledger($form)['difference']) != 0.0) {
            $errors['data.bank_closing'] = 'The difference with the bank statement is '.money($diff, $form->currency_code).'. It must equal zero before you submit.';
        }
        if ($form->type === 'J' && (int) $form->datum('paying_country_id') === $form->country_id) {
            $errors['data.paying_country_id'] = 'Paying and receiving branch must be different.';
        }
        if (! empty($def['requires_attachment']) && ! $form->documents()->exists() && blank($form->datum('certification_ref'))) {
            $errors['documents'] = 'Attach receipts or invoices, or link a Receipt Reimbursement Certification (policy requirement).';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function total(FinanceForm $form): float
    {
        return match (true) {
            $form->type === 'F' => (float) $form->budgetLines()->sum('amount'),
            $form->type === 'G' => (float) $form->lines()->sum('outflow'),
            default => (float) $form->lines()->sum('amount'),
        };
    }

    /** Appendix G running balance and reconciliation. */
    public function ledger(FinanceForm $form): array
    {
        $balance = (float) $form->datum('opening_balance', 0);
        $rows = [];
        $in = $out = 0.0;
        foreach ($form->lines as $l) {
            $in += (float) $l->inflow;
            $out += (float) $l->outflow;
            $balance += (float) $l->inflow - (float) $l->outflow;
            $rows[] = ['line' => $l, 'balance' => round($balance, 2)];
        }

        return ['rows' => $rows, 'inflow' => $in, 'outflow' => $out, 'closing' => round($balance, 2),
            'difference' => round($balance - (float) $form->datum('bank_closing', 0), 2)];
    }

    /** Appendix A: post each line to Expenses once paid. Feeds budget monitoring and branch reports. */
    public function markPaid(FinanceForm $form, User $user, string $paidOn, ?string $reference): void
    {
        $this->expect($form, 'A', 'approved');
        DB::transaction(function () use ($form, $user, $paidOn, $reference) {
            foreach ($form->lines as $line) {
                $this->postExpense($form, $line->expense_category_id, $line->description, (float) $line->amount, $paidOn, $user);
            }
            $form->update(['status' => 'paid', 'data' => array_merge($form->data, ['pay_date' => $paidOn, 'payment_reference' => $reference])]);
        });
    }

    public function reimburse(FinanceForm $form): void
    {
        $this->expect($form, 'B', 'approved');
        DB::transaction(fn () => $form->update(['status' => 'reimbursed', 'data' => array_merge($form->data, ['reimbursed_on' => today()->toDateString()])]));
    }

    public function disburse(FinanceForm $form): void
    {
        $this->expect($form, 'K', 'approved');
        DB::transaction(fn () => $form->update(['status' => 'awaiting_liquidation', 'data' => array_merge($form->data, ['disbursed_on' => today()->toDateString()])])); // posts the advance
    }

    public function liquidate(FinanceForm $form, float $spent, User $user): void
    {
        $this->expect($form, 'K', 'awaiting_liquidation');
        $total = (float) $form->total;
        if ($spent > $total) {
            throw ValidationException::withMessages(['spent' => 'Spent cannot exceed the advance. Raise an expense memo for the extra.']);
        }
        DB::transaction(function () use ($form, $spent, $total, $user) {
            if ($spent > 0) {
                $this->postExpense($form, $form->lines->first()?->expense_category_id, $form->datum('subject').' (cash advance)', $spent, today()->toDateString(), $user);
            }
            $form->update(['status' => 'liquidated', 'data' => array_merge($form->data, ['liquidation' => ['spent' => $spent, 'returned' => round($total - $spent, 2), 'date' => today()->toDateString()]])]);
        });
    }

    public function settle(FinanceForm $form): void
    {
        $this->expect($form, 'J', 'approved');
        DB::transaction(fn () => $form->update(['status' => 'settled'])); // posts the intercompany repayment
    }

    public function addWorkingDays(string $from, int $days): Carbon
    {
        $d = Carbon::parse($from);
        while ($days > 0) {
            $d->addDay();
            if (! $d->isWeekend()) {
                $days--;
            }
        }

        return $d;
    }

    private function saveLines(FinanceForm $form, array $lines): void
    {
        $form->lines()->delete();
        $pos = 0;
        foreach ($lines as $l) {
            $amount = (float) ($l['amount'] ?? 0);
            $in = (float) ($l['inflow'] ?? 0);
            $out = (float) ($l['outflow'] ?? 0);
            if (blank($l['description'] ?? null) && $amount == 0.0 && $in == 0.0 && $out == 0.0) {
                continue;
            }
            $category = $l['category'] ?? null;
            $form->lines()->create([
                'position' => $pos++,
                'line_date' => $l['date'] ?? null,
                'description' => $l['description'] ?? null,
                'expense_category_id' => $category && $category !== 'inflow' ? (int) $category : null,
                'is_inflow' => $category === 'inflow',
                'amount' => $amount,
                'inflow' => $in,
                'outflow' => $out,
                'account_details' => $l['account_details'] ?? null,
                'memo_ref' => $l['memo_ref'] ?? null,
                'party' => $l['party'] ?? null,
                'notes' => $l['notes'] ?? null,
            ]);
        }
    }

    /** $budget[category_id][month] = amount */
    private function saveBudget(FinanceForm $form, array $budget, array $notes): void
    {
        $form->budgetLines()->delete();
        foreach ($budget as $categoryId => $months) {
            foreach ((array) $months as $month => $amount) {
                if ((float) $amount > 0 || ! empty($notes[$categoryId])) {
                    $form->budgetLines()->create(['expense_category_id' => (int) $categoryId, 'month' => (int) $month, 'amount' => (float) $amount, 'note' => $notes[$categoryId] ?? null]);
                }
            }
        }
    }

    private function postExpense(FinanceForm $form, ?int $categoryId, ?string $description, float $amount, string $on, User $user): void
    {
        Expense::create([
            'number' => $this->numbers->document($form->country, 'EXP'),
            'country_id' => $form->country_id,
            'branch_id' => $form->branch_id,
            'expense_category_id' => $categoryId ?? ExpenseCategory::where('name', 'Other (Specify)')->value('id'),
            'description' => $description ?? $form->reference,
            'amount' => $amount,
            'currency_code' => $form->currency_code,
            'exchange_rate' => $form->exchange_rate,
            'spent_on' => $on,
            'payment_method' => $form->datum('pay_method'),
            'status' => 'paid',
            'source_type' => $form->getMorphClass(),
            'source_id' => $form->id,
            'submitted_by' => $form->prepared_by,
            'approved_by' => $user->id,
        ]);
    }

    private function expect(FinanceForm $form, string $type, string $status): void
    {
        if ($form->type !== $type || $form->status !== $status) {
            throw ValidationException::withMessages(['status' => 'That action is not available for this form right now.']);
        }
    }
}
