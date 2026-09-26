<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'customer' => $this->whenLoaded('customer', fn () => ['code' => $this->customer->code, 'name' => $this->customer->displayName()]),
            'currency' => $this->currency_code,
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->displayStatus(),
            'total' => (float) $this->total,
            'paid' => (float) $this->amount_paid,
            'balance' => $this->balance(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map->only(['description', 'quantity', 'unit_price', 'discount_pct', 'tax_pct', 'line_total'])),
        ];
    }
}
