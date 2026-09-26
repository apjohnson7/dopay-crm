<?php

namespace App\Services\Accounting;

use App\Models\Country;
use App\Models\Expense;
use App\Models\FinanceForm;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ExchangeRateService;
use Carbon\Carbon;

/**
 * How each business event becomes a journal entry. Called by the model observers, and by
 * `php artisan dopay:ledger-rebuild` to post history. Every method is safe to call twice.
 */
class PostingRules
{
    public function __construct(private LedgerService $ledger, private ExchangeRateService $rates) {}

    private function local(float $amount, string $from, Country $country, $on): float
    {
        return $from === $country->currency_code ? $amount : $this->rates->convert($amount, $from, $country->currency_code, $on);
    }

    private function cashRole(?string $method): string
    {
        return config('accounting.method_roles.'.$method, 'bank');
    }

    /** Approved invoice: Dr receivables, Cr sales (goods / services) and VAT payable. */
    public function invoice(Invoice $invoice): void
    {
        if (! in_array($invoice->status, ['approved', 'sent'], true)) {
            return;
        }
        $invoice->loadMissing('items.product', 'country', 'customer');
        $country = $invoice->country;
        $goods = $services = 0.0;
        foreach ($invoice->items as $item) {
            $net = (float) $item->quantity * (float) $item->unit_price * (1 - (float) $item->discount_pct / 100);
            str_starts_with((string) $item->product?->sku, 'S-') ? $services += $net : $goods += $net;
        }
        $c = fn ($v) => $this->local((float) $v, $invoice->currency_code, $country, $invoice->issue_date);
        $this->ledger->post($country, $invoice->issue_date, 'Invoice '.$invoice->number.' · '.$invoice->customer->displayName(), [
            ['ar', $c($invoice->total), 0], ['sales_goods', 0, $c($goods)], ['sales_services', 0, $c($services)], ['vat', 0, $c($invoice->tax_total)],
        ], 'auto', 'invoice:'.$invoice->id, $invoice);
    }

    public function invoiceCancelled(Invoice $invoice): void
    {
        $this->ledger->reverse('invoice:'.$invoice->id, $invoice->updated_at ?? now(), 'Cancelled invoice '.$invoice->number);
    }

    /** Payment received: Dr cash / bank / mobile money, Cr receivables. */
    public function payment(Payment $payment): void
    {
        if ($payment->status === 'failed') {
            return;
        }
        $payment->loadMissing('customer');
        $country = Country::findOrFail($payment->country_id);
        $amount = $this->local((float) $payment->amount, $payment->currency_code, $country, $payment->paid_on);
        $this->ledger->post($country, $payment->paid_on, 'Payment '.$payment->number.' · '.$payment->customer->displayName().($payment->reference ? ' · '.$payment->reference : ''), [
            [$this->cashRole($payment->method), $amount, 0, $payment->reference], ['ar', 0, $amount],
        ], 'auto', 'payment:'.$payment->id, $payment);
    }

    public function paymentReversed(Payment $payment): void
    {
        $this->ledger->reverse('payment:'.$payment->id, $payment->updated_at ?? now(), 'Reversal of payment '.$payment->number);
    }

    /** Approved expense accrues (Dr expense, Cr accrued); paid expense clears the accrual or is posted straight to cash. */
    public function expense(Expense $expense): void
    {
        if (! in_array($expense->status, ['approved', 'paid'], true)) {
            return;
        }
        $expense->loadMissing('category');
        $country = Country::findOrFail($expense->country_id);
        $amount = $this->local((float) $expense->amount, $expense->currency_code, $country, $expense->spent_on);
        $debit = config('accounting.category_roles.'.$expense->category?->name, 'exp_other');
        if ($expense->category?->name === 'Tax') {
            $debit = preg_match('/\b(VAT|TVA)\b/i', $expense->description) ? 'vat' : (preg_match('/\bPAYE\b/i', $expense->description) ? 'paye' : 'exp_tax');
        }
        $fromAdvance = $expense->source_type === (new FinanceForm)->getMorphClass() && FinanceForm::whereKey($expense->source_id)->value('type') === 'K';
        $credit = $fromAdvance ? 'advances' : $this->cashRole($expense->payment_method);
        $memo = 'Expense '.$expense->number.' · '.$expense->description;

        if ($expense->status === 'approved') {
            $this->ledger->post($country, $expense->spent_on, 'Accrued · '.$memo, [[$debit, $amount, 0], ['accrued', 0, $amount]], 'auto', 'expense:'.$expense->id.':accrual', $expense);

            return;
        }
        $accrued = \App\Models\JournalEntry::where('source_key', 'expense:'.$expense->id.':accrual')->exists();
        $accrued
            ? $this->ledger->post($country, $expense->updated_at ?? now(), 'Paid · '.$memo, [['accrued', $amount, 0], [$credit, 0, $amount]], 'auto', 'expense:'.$expense->id.':payment', $expense)
            : $this->ledger->post($country, $expense->spent_on, $memo, [[$debit, $amount, 0], [$credit, 0, $amount]], 'auto', 'expense:'.$expense->id, $expense);
    }

    /** Cash advance (Appendix K): disbursed = Dr staff advances, Cr cash; unused money returned = Dr cash, Cr staff advances. */
    public function advance(FinanceForm $form): void
    {
        if ($form->type !== 'K' || ! in_array($form->status, ['awaiting_liquidation', 'liquidated', 'settled'], true)) {
            return;
        }
        $country = $form->country;
        $cash = $this->cashRole($form->datum('pay_method'));
        $on = $form->datum('disbursed_on') ?? now()->toDateString();
        $total = $this->local((float) $form->total, $form->currency_code, $country, $on);
        $this->ledger->post($country, $on, 'Cash advance '.$form->reference, [['advances', $total, 0], [$cash, 0, $total]], 'auto', 'advance:'.$form->id, $form);
        $returned = (float) data_get($form->data, 'liquidation.returned', 0);
        if ($form->status !== 'awaiting_liquidation' && $returned > 0) {
            $r = $this->local($returned, $form->currency_code, $country, data_get($form->data, 'liquidation.date'));
            $this->ledger->post($country, data_get($form->data, 'liquidation.date', now()), 'Unused advance returned · '.$form->reference, [[$cash, $r, 0], ['advances', 0, $r]], 'auto', 'advance:'.$form->id.':return', $form);
        }
    }

    /** Interbranch memo (Appendix J): the paying country lends, the receiving country owes. Both sides post in their own currency. */
    public function interbranch(FinanceForm $form): void
    {
        if ($form->type !== 'J') {
            return;
        }
        if (in_array($form->status, ['draft', 'returned', 'void'], true)) {
            $this->interbranchUndo($form); // a revised or voided memo takes its postings back out of both countries' books

            return;
        }
        if (! in_array($form->status, ['approved', 'paid', 'settled'], true)) {
            return;
        }
        $receiving = $form->country;
        $paying = Country::find((int) $form->datum('paying_country_id'));
        if (! $paying || $paying->id === $receiving->id) {
            return;
        }
        $on = $form->approved_at ?? now();
        $pay = $this->local((float) $form->total, $form->currency_code, $paying, $on);
        $rec = $this->local((float) $form->total, $form->currency_code, $receiving, $on);
        $this->ledger->post($paying, $on, 'Funds sent to DoPay '.$receiving->name.' · '.$form->reference, [['ico_rec', $pay, 0, $receiving->iso2], ['bank', 0, $pay]], 'auto', $this->ibKey($form).':paying', $form);
        $this->ledger->post($receiving, $on, 'Funds received from DoPay '.$paying->name.' · '.$form->reference, [['bank', $rec, 0], ['ico_pay', 0, $rec, $paying->iso2]], 'auto', $this->ibKey($form).':receiving', $form);
        if ($form->status === 'settled') {
            $this->ledger->post($paying, now(), 'Repayment from DoPay '.$receiving->name.' · '.$form->reference, [['bank', $pay, 0], ['ico_rec', 0, $pay, $receiving->iso2]], 'auto', $this->ibKey($form).':paying:settled', $form);
            $this->ledger->post($receiving, now(), 'Repayment to DoPay '.$paying->name.' · '.$form->reference, [['ico_pay', $rec, 0, $paying->iso2], ['bank', 0, $rec]], 'auto', $this->ibKey($form).':receiving:settled', $form);
        }
    }

    private function ibKey(FinanceForm $form): string
    {
        return 'interbranch:'.$form->id.':v'.$form->version;
    }

    /** Reverses whatever the memo posted in any earlier version that has not been reversed yet. */
    private function interbranchUndo(FinanceForm $form): void
    {
        \App\Models\JournalEntry::where('source_key', 'like', 'interbranch:'.$form->id.':%')->where('source_key', 'not like', '%:reversal')->pluck('source_key')
            ->reject(fn ($k) => \App\Models\JournalEntry::where('source_key', $k.':reversal')->exists())
            ->each(fn ($k) => $this->ledger->reverse($k, now(), 'Reversed: '.$form->reference.' was '.($form->status === 'void' ? 'voided' : 'sent back for revision')));
    }

    /** Petty cash voucher (Appendix B) reimbursed: each line is an expense paid from petty cash, and the float is topped up from the bank. */
    public function pettyCash(FinanceForm $form): void
    {
        if ($form->type !== 'B' || $form->status !== 'reimbursed') {
            return;
        }
        $form->loadMissing('lines.category', 'country');
        $country = $form->country;
        $on = $form->datum('reimbursed_on') ?? now($country->timezone)->toDateString();
        $lines = [];
        $total = 0.0;
        foreach ($form->lines as $l) {
            $amount = $this->local((float) $l->amount, $form->currency_code, $country, $on);
            if ($amount <= 0) {
                continue;
            }
            $lines[] = [config('accounting.category_roles.'.$l->category?->name, 'exp_other'), $amount, 0, $l->description];
            $total += $amount;
        }
        if (! $lines) {
            return;
        }
        $lines[] = ['petty', 0, $total];
        $this->ledger->post($country, $on, 'Petty cash spent · '.$form->reference, $lines, 'auto', 'petty:'.$form->id.':v'.$form->version, $form);
        $this->ledger->post($country, $on, 'Petty cash float reimbursed · '.$form->reference, [['petty', $total, 0], ['bank', 0, $total]], 'auto', 'petty:'.$form->id.':v'.$form->version.':topup', $form);
    }

    /** Posts everything already recorded for a country (first install on existing data). Idempotent. */
    public function rebuild(Country $country): int
    {
        $before = \App\Models\JournalEntry::where('country_id', $country->id)->count();
        Invoice::where('country_id', $country->id)->whereIn('status', ['approved', 'sent', 'cancelled'])->orderBy('issue_date')->each(function (Invoice $i) {
            if ($i->status === 'cancelled' && ! $i->approved_at) {
                return;
            }
            $status = $i->status;
            $i->status = $status === 'cancelled' ? 'approved' : $status;
            $this->invoice($i);
            $i->status = $status;
            if ($status === 'cancelled') {
                $this->invoiceCancelled($i);
            }
        });
        Payment::where('country_id', $country->id)->orderBy('paid_on')->each(function (Payment $p) {
            $this->payment($p);
            if ($p->status === 'reversed') {
                $this->paymentReversed($p);
            }
        });
        Expense::where('country_id', $country->id)->whereIn('status', ['approved', 'paid'])->orderBy('spent_on')->each(fn ($e) => $this->expense($e));
        FinanceForm::where('country_id', $country->id)->whereIn('type', ['B', 'K', 'J'])->each(fn ($f) => match ($f->type) { 'B' => $this->pettyCash($f), 'K' => $this->advance($f), default => $this->interbranch($f) });

        return \App\Models\JournalEntry::where('country_id', $country->id)->count() - $before;
    }
}
