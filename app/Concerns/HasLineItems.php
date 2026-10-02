<?php

namespace App\Concerns;

/**
 * Documents built from priced lines (quotes, sales orders, credit notes) share one way of adding up:
 * each line is quantity × unit price, less its discount, plus tax on what is left.
 */
trait HasLineItems
{
    public function recalculateTotals(): static
    {
        $sub = $disc = $tax = 0.0;
        foreach ($this->items()->get() as $item) {
            $gross = (float) $item->quantity * (float) $item->unit_price;
            $d = $gross * (float) $item->discount_pct / 100;
            $t = ($gross - $d) * (float) $item->tax_pct / 100;
            if (array_key_exists('line_total', $item->getAttributes()) || $item->isFillable('line_total')) {
                $item->update(['line_total' => round($gross - $d, 2)]);
            }
            $sub += $gross;
            $disc += $d;
            $tax += $t;
        }
        $this->update(['subtotal' => round($sub, 2), 'discount_total' => round($disc, 2), 'tax_total' => round($tax, 2), 'total' => round($sub - $disc + $tax, 2)]);

        return $this->refresh();
    }

    /** Net of discount, split into goods and services (service SKUs start with S-), for posting to the right sales account. */
    public function netByKind(): array
    {
        $goods = $services = 0.0;
        foreach ($this->items as $item) {
            $net = (float) $item->quantity * (float) $item->unit_price * (1 - (float) $item->discount_pct / 100);
            str_starts_with((string) $item->product?->sku, 'S-') ? $services += $net : $goods += $net;
        }

        return ['goods' => $goods, 'services' => $services];
    }
}
