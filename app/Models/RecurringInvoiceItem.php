<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringInvoiceItem extends Model
{
    protected $fillable = ['recurring_invoice_id', 'product_id', 'position', 'description', 'quantity', 'unit_price', 'discount_pct', 'tax_pct'];

    protected $casts = ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_pct' => 'decimal:2', 'tax_pct' => 'decimal:3'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
