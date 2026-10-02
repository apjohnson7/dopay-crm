<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasLineItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Corrects an issued invoice (returns, price corrections, errors). Issued invoices are never edited. */
class CreditNote extends Model
{
    use Auditable, HasLineItems;

    public const REASONS = ['Goods returned', 'Price adjustment', 'Invoice error', 'Discount after sale', 'Service not delivered'];

    protected $fillable = ['number', 'invoice_id', 'customer_id', 'country_id', 'branch_id', 'currency_code', 'exchange_rate', 'issue_date', 'reason_type', 'reason', 'status', 'subtotal', 'discount_total', 'tax_total', 'total', 'amount_applied', 'refund_due', 'refunded_amount', 'refund_method', 'refund_reference', 'refunded_on', 'refund_authorized_by', 'rejected_reason', 'created_by', 'approved_by', 'approved_at'];

    protected $casts = [
        'issue_date' => 'date', 'refunded_on' => 'date', 'approved_at' => 'datetime', 'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2',
        'amount_applied' => 'decimal:2', 'refund_due' => 'decimal:2', 'refunded_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class)->orderBy('position');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fiscal(): MorphOne
    {
        return $this->morphOne(FiscalDocument::class, 'document');
    }

    public function displayStatus(): string
    {
        return match (true) {
            $this->status === 'pending_approval' => 'Pending approval',
            $this->status === 'rejected' => 'Rejected',
            (float) $this->refund_due > 0.004 => 'Refund due',
            (float) $this->refunded_amount > 0 => 'Refunded',
            default => 'Approved',
        };
    }
}
