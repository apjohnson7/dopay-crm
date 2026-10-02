<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private NumberingService $numbers, private ExchangeRateService $rates) {}

    /**
     * Records a payment, allocates it to invoices and issues the receipt in one transaction.
     * $allocations: [invoice_id => amount]
     */
    public function record(Customer $customer, array $data, array $allocations, User $user): Payment
    {
        return DB::transaction(function () use ($customer, $data, $allocations, $user) {
            $amount = round((float) $data['amount'], 2);
            $allocations = array_filter(array_map(fn ($v) => round((float) $v, 2), $allocations), fn ($v) => $v > 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter the amount received.']);
            }
            if (array_sum($allocations) > $amount + 0.004) {
                throw ValidationException::withMessages(['allocations' => 'Allocations add up to more than the amount received.']);
            }
            if (($data['method'] ?? 'Cash') !== 'Cash' && empty($data['reference'])) {
                throw ValidationException::withMessages(['reference' => 'Add the transaction reference for non-cash payments.']);
            }

            $payment = Payment::create([
                'number' => $this->numbers->document($customer->country, 'PAY'),
                'customer_id' => $customer->id,
                'country_id' => $customer->country_id,
                'branch_id' => $customer->branch_id,
                'currency_code' => $customer->currency_code,
                'exchange_rate' => $this->rates->rate($customer->currency_code, $data['paid_on']),
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_on' => $data['paid_on'],
                'status' => 'completed',
                'received_by' => $user->id,
            ]);

            foreach ($allocations as $invoiceId => $value) {
                $invoice = Invoice::lockForUpdate()->where('customer_id', $customer->id)->findOrFail($invoiceId);
                if (! $invoice->isOfficial()) {
                    throw ValidationException::withMessages(['allocations' => "{$invoice->number} is not approved yet."]);
                }
                if ($value > $invoice->balance() + 0.004) {
                    throw ValidationException::withMessages(['allocations' => "{$invoice->number} only has ".money($invoice->balance(), $invoice->currency_code).' outstanding.']);
                }
                $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $value]);
                $invoice->increment('amount_paid', $value);
            }

            $payment->receipt()->create([
                'number' => $this->numbers->document($customer->country, 'RCT'),
                'issued_on' => $data['paid_on'],
                'status' => 'issued',
            ]);

            return $payment->load('receipt', 'allocations.invoice');
        });
    }

    /**
     * A payment confirmed by an online provider: recorded against one invoice with no staff member, the provider's fee kept
     * so it posts to bank charges, and the receipt issued. Idempotent on the provider reference: a repeated
     * notification returns the payment already recorded instead of recording it twice.
     */
    public function recordOnline(Invoice $invoice, float $amount, string $method, string $reference, \App\Models\PaymentGateway $gateway, float $fee = 0.0): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $reference, $gateway, $fee) {
            $existing = Payment::where('payment_gateway_id', $gateway->id)->where('reference', $reference)->where('status', 'completed')->first();
            if ($existing) {
                return $existing->load('receipt', 'allocations.invoice');
            }
            $invoice = Invoice::with('customer.country')->lockForUpdate()->findOrFail($invoice->id);
            $amount = round($amount, 2);
            if (! $invoice->isOfficial() || $amount <= 0 || $amount > $invoice->balance() + 0.004) {
                throw ValidationException::withMessages(['amount' => "{$invoice->number} can't take ".money($amount, $invoice->currency_code).' (balance '.money($invoice->balance(), $invoice->currency_code).').']);
            }
            $customer = $invoice->customer;
            $today = now($customer->country->timezone)->toDateString();
            $payment = Payment::create([
                'number' => $this->numbers->document($customer->country, 'PAY'),
                'customer_id' => $customer->id, 'country_id' => $invoice->country_id, 'branch_id' => $invoice->branch_id,
                'currency_code' => $invoice->currency_code, 'exchange_rate' => $this->rates->rate($invoice->currency_code, $today),
                'amount' => $amount, 'method' => $method, 'reference' => $reference, 'paid_on' => $today, 'status' => 'completed',
                'payment_gateway_id' => $gateway->id, 'fee' => round($fee, 2), 'received_by' => null,
            ]);
            $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $amount]);
            $invoice->increment('amount_paid', $amount);
            if ($invoice->status === 'approved' && $invoice->generated_at) {
                $invoice->update(['status' => 'sent', 'sent_at' => $invoice->sent_at ?? now()]);
            }
            $payment->receipt()->create(['number' => $this->numbers->document($customer->country, 'RCT'), 'issued_on' => $today, 'status' => 'issued']);

            return $payment->load('receipt', 'allocations.invoice');
        });
    }

    /** Reversal needs a second person's authorization (checked by the controller). */
    public function reverse(Payment $payment, User $by, string $reason): void
    {
        DB::transaction(function () use ($payment, $by, $reason) {
            if ($payment->status !== 'completed') {
                throw ValidationException::withMessages(['status' => 'Only completed payments can be reversed.']);
            }
            foreach ($payment->allocations as $a) {
                $a->invoice->decrement('amount_paid', (float) $a->amount);
            }
            $payment->update(['status' => 'reversed', 'reversed_by' => $by->id, 'reversal_reason' => $reason]);
            $payment->receipt?->update(['status' => 'void']);
        });
    }
}
