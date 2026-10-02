<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;

/**
 * A customer's statement of account: official invoices (debit), completed payments and approved credit notes (credit),
 * and refunds paid back to the customer (debit), with an opening balance and a running balance.
 * Used on the customer page and in the customer portal.
 */
class StatementService
{
    public function build(Customer $customer, $from, $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();
        $tx = collect();
        foreach (Invoice::where('customer_id', $customer->id)->whereIn('status', ['approved', 'sent'])->get() as $i) {
            $tx->push(['date' => $i->issue_date, 'ref' => $i->number, 'kind' => 'invoice', 'desc' => 'Invoice', 'debit' => (float) $i->total, 'credit' => 0.0]);
        }
        foreach (Payment::where('customer_id', $customer->id)->where('status', 'completed')->get() as $p) {
            $tx->push(['date' => $p->paid_on, 'ref' => $p->number, 'kind' => 'payment', 'desc' => 'Payment · '.$p->method, 'debit' => 0.0, 'credit' => (float) $p->amount]);
        }
        foreach (CreditNote::where('customer_id', $customer->id)->where('status', 'approved')->with('invoice')->get() as $n) {
            $tx->push(['date' => $n->issue_date, 'ref' => $n->number, 'kind' => 'credit_note', 'desc' => 'Credit note · '.$n->invoice?->number, 'debit' => 0.0, 'credit' => (float) $n->total]);
            if ((float) $n->refunded_amount > 0) {
                $tx->push(['date' => $n->refunded_on ?? $n->updated_at, 'ref' => $n->number, 'kind' => 'refund', 'desc' => 'Refund · '.$n->refund_method, 'debit' => (float) $n->refunded_amount, 'credit' => 0.0]);
            }
        }
        $tx = $tx->sortBy(fn ($t) => Carbon::parse($t['date'])->timestamp)->values();
        $opening = $tx->filter(fn ($t) => Carbon::parse($t['date'])->lt($from))->sum(fn ($t) => $t['debit'] - $t['credit']);
        $bal = $opening;
        $rows = $tx->filter(fn ($t) => Carbon::parse($t['date'])->betweenIncluded($from, $to))->map(function ($t) use (&$bal) {
            $bal += $t['debit'] - $t['credit'];

            return $t + ['balance' => round($bal, 2)];
        })->values();

        return ['from' => $from, 'to' => $to, 'opening' => round($opening, 2), 'rows' => $rows, 'closing' => round($bal, 2)];
    }
}
