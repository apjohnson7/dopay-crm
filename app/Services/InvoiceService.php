<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private NumberingService $numbers, private ExchangeRateService $rates) {}

    /**
     * Create or update a draft. $data: customer_id, issue_date, due_date, notes, terms, items[] (product_id, description, quantity, unit_price, discount_pct, tax_pct)
     */
    public function saveDraft(array $data, User $user, ?Invoice $invoice = null): Invoice
    {
        return DB::transaction(function () use ($data, $user, $invoice) {
            $customer = Customer::with('country')->findOrFail($data['customer_id']);
            if (! $user->canActForCountry($customer->country_id)) {
                throw ValidationException::withMessages(['customer_id' => 'You can only invoice customers in your own country.']);
            }
            $items = collect($data['items'] ?? [])->filter(fn ($i) => trim($i['description'] ?? '') !== '' && (float) ($i['quantity'] ?? 0) > 0);
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one line with a description and quantity.']);
            }

            $invoice ??= new Invoice([
                'number' => $this->numbers->document($customer->country, 'INV'),
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
            if ($invoice->exists && $invoice->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Approved invoices can only be changed through an authorized revision.']);
            }
            $invoice->fill([
                'customer_id' => $customer->id,
                'country_id' => $customer->country_id,
                'branch_id' => $customer->branch_id,
                'currency_code' => $customer->currency_code,
                'exchange_rate' => $this->rates->rate($customer->currency_code, $data['issue_date']),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? now()->parse($data['issue_date'])->addDays($customer->payment_terms_days),
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ])->save();

            $invoice->items()->delete();
            foreach ($items->values() as $pos => $i) {
                $invoice->items()->create([
                    'product_id' => $i['product_id'] ?? null,
                    'position' => $pos,
                    'description' => $i['description'],
                    'quantity' => $i['quantity'],
                    'unit_price' => $i['unit_price'] ?? 0,
                    'discount_pct' => $i['discount_pct'] ?? 0,
                    'tax_pct' => $i['tax_pct'] ?? $customer->country->tax_rate,
                ]);
            }

            return $this->recalculate($invoice);
        });
    }

    public function recalculate(Invoice $invoice): Invoice
    {
        $sub = $disc = $tax = 0.0;
        foreach ($invoice->items()->get() as $item) {
            $gross = (float) $item->quantity * (float) $item->unit_price;
            $d = $gross * (float) $item->discount_pct / 100;
            $t = ($gross - $d) * (float) $item->tax_pct / 100;
            $item->update(['line_total' => round($gross - $d, 2)]);
            $sub += $gross;
            $disc += $d;
            $tax += $t;
        }
        $invoice->update([
            'subtotal' => round($sub, 2), 'discount_total' => round($disc, 2), 'tax_total' => round($tax, 2),
            'total' => round($sub - $disc + $tax, 2),
        ]);

        return $invoice->refresh();
    }

    public function submit(Invoice $invoice): void
    {
        $this->expect($invoice, 'draft');
        $invoice->update(['status' => 'pending_approval']);
    }

    public function approve(Invoice $invoice, User $approver): void
    {
        $this->expect($invoice, 'pending_approval');
        if (! $approver->can('invoices.approve') || ! $approver->canActForCountry($invoice->country_id)) {
            abort(403, 'You cannot approve invoices for this country.');
        }
        $invoice->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
    }

    public function returnToDraft(Invoice $invoice, string $comment): void
    {
        $this->expect($invoice, 'pending_approval');
        $invoice->update(['status' => 'draft']);
        AuditLogger::log('Returned invoice to draft', $invoice, null, ['comment' => $comment]);
    }

    /** Locks the numbers and issues the secure share link. */
    public function generate(Invoice $invoice): void
    {
        $this->expect($invoice, 'approved');
        $invoice->update(['generated_at' => now(), 'share_token' => $invoice->share_token ?? Str::random(40)]);
    }

    public function markSent(Invoice $invoice): void
    {
        if ($invoice->status === 'approved' && $invoice->generated_at) {
            $invoice->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    public function cancel(Invoice $invoice, string $reason): void
    {
        if ((float) $invoice->amount_paid > 0) {
            throw ValidationException::withMessages(['status' => 'Reverse the payments on this invoice before cancelling it.']);
        }
        $invoice->update(['status' => 'cancelled', 'cancelled_reason' => $reason, 'share_token' => null]); // the customer link stops working immediately
    }

    private function expect(Invoice $invoice, string $status): void
    {
        if ($invoice->status !== $status) {
            throw ValidationException::withMessages(['status' => "This invoice is {$invoice->displayStatus()}, so that step isn't available."]);
        }
    }
}
