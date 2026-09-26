<?php

use App\Models\Currency;
use Illuminate\Support\Carbon;

if (! function_exists('money')) {
    /** Format an amount for its currency: UGX 1,250,000 / USD 1,354.00 */
    function money(float|int|string|null $amount, string $currency, bool $withCode = true): string
    {
        $n = number_format((float) $amount, Currency::decimalsFor($currency));

        return $withCode ? $currency.' '.$n : $n;
    }
}

if (! function_exists('fdate')) {
    function fdate($date, string $format = 'j M Y'): string
    {
        return $date ? Carbon::parse($date)->format($format) : '—';
    }
}

if (! function_exists('status_tone')) {
    /** Maps a status label to a pill colour class used in the UI. */
    function status_tone(string $label): string
    {
        return match ($label) {
            'Paid', 'Completed', 'Issued', 'Active', 'Delivered', 'Reimbursed', 'Liquidated', 'Settled', 'Connected', 'Balanced', 'Ready' => 'ok',
            'Pending approval', 'In approval', 'Submitted', 'Queued', 'Limited', 'Due' => 'warn',
            'Overdue', 'Failed', 'Reversed', 'Returned', 'Cancelled void', 'Out of balance' => 'bad',
            'Approved', 'Partially paid', 'Reviewed', 'Awaiting liquidation' => 'info',
            'Sent', 'Opened' => 'acc',
            default => 'mute',
        };
    }
}

if (! function_exists('dopay_form')) {
    function dopay_form(string $type): array
    {
        return config('dopay.forms.'.$type);
    }
}
