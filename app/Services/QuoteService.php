<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Quote → accepted → sales order → invoice. Each step copies the lines forward, so nothing is typed twice.
 * Quotes don't touch the ledger; the invoice they become does, once it is approved.
 */
class QuoteService
{
    public function __construct(private NumberingService $numbers, private ExchangeRateService $rates, private InvoiceService $invoices) {}

    /** Create or update a quote. $data: customer_id, issue_date, valid_until, notes, terms, items[] (as for invoices). */
    public function save(array $data, User $user, ?Quote $quote = null): Quote
    {
        return DB::transaction(function () use ($data, $user, $quote) {
            $customer = Customer::with('country')->findOrFail($data['customer_id']);
            if (! $user->canActForCountry($customer->country_id)) {
                throw ValidationException::withMessages(['customer_id' => 'You can only quote customers in your own country.']);
            }
            $items = collect($data['items'] ?? [])->filter(fn ($i) => trim($i['description'] ?? '') !== '' && (float) ($i['quantity'] ?? 0) > 0);
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one line with a description and quantity.']);
            }
            if ($quote && ! in_array($quote->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages(['status' => 'Only draft or sent quotes can be edited.']);
            }
            $issue = $data['issue_date'];
            $quote ??= new Quote(['kind' => 'quote', 'number' => $this->numbers->document($customer->country, 'QUO'), 'status' => 'draft', 'created_by' => $user->id, 'access_token' => Str::random(40)]);
            if ($quote->exists) {
                $quote->version = $quote->version + 1;
            }
            $quote->fill([
                'customer_id' => $customer->id, 'country_id' => $customer->country_id, 'branch_id' => $customer->branch_id,
                'currency_code' => $customer->currency_code, 'exchange_rate' => $this->rates->rate($customer->currency_code, $issue),
                'issue_date' => $issue, 'valid_until' => $data['valid_until'] ?? now()->parse($issue)->addDays(30)->toDateString(),
                'notes' => $data['notes'] ?? null, 'terms' => $data['terms'] ?? null,
            ])->save();
            $this->replaceItems($quote, $items->values()->all(), (float) $customer->country->tax_rate);

            return $quote->recalculateTotals();
        });
    }

    public function markSent(Quote $quote): void
    {
        $this->expect($quote, ['draft', 'sent']);
        $quote->update(['status' => 'sent', 'sent_at' => $quote->sent_at ?? now(), 'access_token' => $quote->access_token ?: Str::random(40)]);
    }

    /** Staff record that the customer accepted (by phone, email, signed copy). */
    public function accept(Quote $quote, User $by, ?string $reference = null): void
    {
        $this->expectOpen($quote);
        $quote->update(['status' => 'accepted', 'accepted_at' => now(), 'accepted_by' => $by->id, 'customer_reference' => $reference ?: $quote->customer_reference]);
    }

    /** The customer accepts in the portal or from the quote link, typing their name. */
    public function acceptByCustomer(Quote $quote, string $name, ?string $reference = null): void
    {
        $this->expectOpen($quote);
        $quote->update(['status' => 'accepted', 'accepted_at' => now(), 'accepted_by_name' => mb_substr(trim($name), 0, 190), 'customer_reference' => $reference ? mb_substr(trim($reference), 0, 80) : $quote->customer_reference]);
        AuditLogger::log('Customer accepted quote', $quote, ['status' => 'sent'], ['status' => 'accepted', 'accepted_by_name' => $quote->accepted_by_name], $quote->number);
    }

    public function decline(Quote $quote, ?string $reason = null): void
    {
        if ($quote->kind !== 'quote' || ! in_array($quote->status, ['sent', 'draft'], true)) {
            throw ValidationException::withMessages(['status' => 'This quote can no longer be declined.']);
        }
        $quote->update(['status' => 'declined', 'decline_reason' => $reason]);
    }

    public function declineByCustomer(Quote $quote, ?string $reason = null): void
    {
        $this->expectOpen($quote);
        $this->decline($quote, $reason ?: 'Declined by the customer');
    }

    public function cancel(Quote $quote): void
    {
        if ($quote->kind === 'quote') {
            $this->expect($quote, ['draft', 'sent']);
        } else {
            $this->expect($quote, ['open']);
        }
        $quote->update(['status' => 'cancelled', 'access_token' => null]);
    }

    public function extend(Quote $quote, int $days = 30): void
    {
        $this->expect($quote, ['sent']);
        $quote->update(['valid_until' => today()->addDays($days)]);
    }

    /** Accepted quote → sales order with the same lines. */
    public function toOrder(Quote $quote, User $by): Quote
    {
        $this->expect($quote, ['accepted']);

        return DB::transaction(function () use ($quote, $by) {
            $order = Quote::create([
                'kind' => 'order', 'number' => $this->numbers->document($quote->country, 'SO'), 'status' => 'open', 'quote_id' => $quote->id,
                'customer_id' => $quote->customer_id, 'country_id' => $quote->country_id, 'branch_id' => $quote->branch_id,
                'currency_code' => $quote->currency_code, 'exchange_rate' => $quote->exchange_rate, 'issue_date' => today(),
                'notes' => $quote->notes, 'terms' => $quote->terms, 'customer_reference' => $quote->customer_reference, 'created_by' => $by->id,
            ]);
            $this->replaceItems($order, $quote->items->map(fn ($i) => $i->only(['product_id', 'description', 'quantity', 'unit_price', 'discount_pct', 'tax_pct']))->all(), 0);
            $order->recalculateTotals();
            $quote->update(['status' => 'converted']);

            return $order;
        });
    }

    /** Accepted quote or open sales order → draft invoice with the same lines, which then follows the normal approval. */
    public function toInvoice(Quote $doc, User $by): Invoice
    {
        $doc->kind === 'quote' ? $this->expect($doc, ['accepted']) : $this->expect($doc, ['open']);

        return DB::transaction(function () use ($doc, $by) {
            $customer = $doc->customer()->first();
            $today = now($doc->country->timezone)->toDateString();
            $invoice = $this->invoices->saveDraft([
                'customer_id' => $doc->customer_id, 'issue_date' => $today, 'due_date' => now()->parse($today)->addDays($customer->payment_terms_days)->toDateString(),
                'notes' => $doc->notes, 'terms' => $doc->terms,
                'items' => $doc->items->map(fn ($i) => $i->only(['product_id', 'description', 'quantity', 'unit_price', 'discount_pct', 'tax_pct']))->all(),
            ], $by);
            $invoice->update($doc->kind === 'quote' ? ['quote_id' => $doc->id] : ['sales_order_id' => $doc->id, 'quote_id' => $doc->quote_id]);
            $doc->update(['status' => $doc->kind === 'quote' ? 'converted' : 'invoiced', 'invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    private function replaceItems(Quote $quote, array $items, float $defaultTax): void
    {
        $quote->items()->delete();
        foreach (array_values($items) as $pos => $i) {
            $quote->items()->create([
                'product_id' => $i['product_id'] ?? null, 'position' => $pos, 'description' => $i['description'],
                'quantity' => $i['quantity'], 'unit_price' => $i['unit_price'] ?? 0, 'discount_pct' => $i['discount_pct'] ?? 0,
                'tax_pct' => $i['tax_pct'] ?? $defaultTax,
            ]);
        }
    }

    /** A quote the customer can still answer: sent and not past its validity date. */
    private function expectOpen(Quote $quote): void
    {
        if ($quote->kind !== 'quote' || $quote->displayStatus() !== 'Sent') {
            throw ValidationException::withMessages(['status' => $quote->displayStatus() === 'Expired' ? 'This quote has expired. Ask us for an updated one.' : 'This quote can no longer be accepted.']);
        }
    }

    private function expect(Quote $quote, array $statuses): void
    {
        if (! in_array($quote->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => "This {$quote->kind} is {$quote->displayStatus()}, so that step isn't available."]);
        }
    }
}
