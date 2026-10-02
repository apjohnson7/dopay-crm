<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    protected $fillable = ['credit_note_id', 'invoice_item_id', 'product_id', 'position', 'description', 'quantity', 'unit_price', 'discount_pct', 'tax_pct', 'line_total'];

    protected $casts = ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_pct' => 'decimal:2', 'tax_pct' => 'decimal:3', 'line_total' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
