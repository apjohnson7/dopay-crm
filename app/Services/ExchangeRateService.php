<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

/** Rates are stored as units of a currency per 1 USD (the base currency). */
class ExchangeRateService
{
    private array $cache = [];

    public function rate(string $currency, $on = null): float
    {
        if ($currency === config('dopay.base_currency')) {
            return 1.0;
        }
        $date = Carbon::parse($on ?? today())->toDateString();
        $key = $currency.'@'.$date;

        return $this->cache[$key] ??= (float) (ExchangeRate::where('currency_code', $currency)
            ->where('effective_on', '<=', $date)->orderByDesc('effective_on')->value('rate_per_usd')
            ?? throw new \RuntimeException("No exchange rate for {$currency} on {$date}. Add one in Settings → Finance."));
    }

    public function convert(float $amount, string $from, string $to, $on = null): float
    {
        if ($from === $to) {
            return $amount;
        }

        return round($amount / $this->rate($from, $on) * $this->rate($to, $on), 2);
    }

    public function toBase(float $amount, string $from, $on = null): float
    {
        return $this->convert($amount, $from, config('dopay.base_currency'), $on);
    }
}
