<?php

namespace App\Services;

/** "Two hundred and ninety-five thousand Uganda shillings only" — used on vouchers and memos. */
class AmountInWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const SCALES = ['', ' thousand', ' million', ' billion', ' trillion'];

    private const CURRENCIES = [
        'UGX' => ['Uganda shilling', 'Uganda shillings', null],
        'KES' => ['Kenya shilling', 'Kenya shillings', 'cents'],
        'NGN' => ['naira', 'naira', 'kobo'],
        'XAF' => ['CFA franc', 'CFA francs', null],
        'XOF' => ['CFA franc', 'CFA francs', null],
        'USD' => ['US dollar', 'US dollars', 'cents'],
    ];

    public static function number(int $n): string
    {
        if ($n === 0) {
            return 'zero';
        }
        $parts = [];
        $i = 0;
        while ($n > 0) {
            $chunk = $n % 1000;
            if ($chunk) {
                array_unshift($parts, self::below1000($chunk).self::SCALES[$i]);
            }
            $n = intdiv($n, 1000);
            $i++;
        }

        return implode(' ', $parts);
    }

    public static function amount(float $amount, string $currency): string
    {
        [$one, $many, $minor] = self::CURRENCIES[$currency] ?? [$currency, $currency, null];
        $whole = (int) floor($amount + 1e-9);
        $fraction = (int) round(($amount - $whole) * 100);
        $s = self::number($whole).' '.($whole === 1 ? $one : $many);
        if ($fraction && $minor) {
            $s .= ' and '.self::number($fraction).' '.$minor;
        }

        return ucfirst($s.' only');
    }

    private static function below1000(int $n): string
    {
        $h = intdiv($n, 100);
        $r = $n % 100;
        $s = $h ? self::ONES[$h].' hundred'.($r ? ' and ' : '') : '';
        if ($r) {
            $s .= $r < 20 ? self::ONES[$r] : self::TENS[intdiv($r, 10)].($r % 10 ? '-'.self::ONES[$r % 10] : '');
        }

        return $s;
    }
}
