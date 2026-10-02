<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Invoice extends Model
{
    use Auditable;

    /** The share token opens the invoice without signing in, so it never goes into the audit trail. */
    protected array $auditExclude = ['share_token'];

    protected $fillable = ['number', 'customer_id', 'country_id', 'branch_id', 'currency_code', 'exchange_rate', 'issue_date', 'due_date', 'status', 'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid', 'amount_credited', 'notes', 'terms', 'quote_id', 'sales_order_id', 'recurring_invoice_id', 'version', 'share_token', 'generated_at', 'sent_at', 'share_refreshed_at', 'created_by', 'approved_by', 'approved_at', 'cancelled_reason'];

    protected $casts = [
        'issue_date' => 'date', 'due_date' => 'date', 'generated_at' => 'datetime', 'sent_at' => 'datetime', 'share_refreshed_at' => 'datetime', 'approved_at' => 'datetime',
        'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'amount_credited' => 'decimal:2', 'exchange_rate' => 'decimal:6',
    ];

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

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function fiscal(): MorphOne
    {
        return $this->morphOne(FiscalDocument::class, 'document');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'sales_order_id');
    }

    public function recurring(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class, 'recurring_invoice_id');
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }

    /** What the customer still owes: total less payments and approved credit notes. */
    public function balance(): float
    {
        return round((float) $this->total - (float) $this->amount_paid - (float) $this->amount_credited, 2);
    }

    public function isOfficial(): bool
    {
        return in_array($this->status, ['approved', 'sent'], true);
    }

    /** Status as the business sees it (Paid / Partially paid / Overdue are derived). */
    public function displayStatus(): string
    {
        return match (true) {
            $this->status === 'draft' => 'Draft',
            $this->status === 'pending_approval' => 'Pending approval',
            $this->status === 'cancelled' => 'Cancelled',
            $this->balance() <= 0.004 => (float) $this->amount_paid > 0 || (float) $this->amount_credited <= 0 ? 'Paid' : 'Credited',
            $this->status === 'approved' && (float) $this->amount_paid == 0.0 => 'Approved',
            $this->due_date->isBefore(today()) => 'Overdue',
            (float) $this->amount_paid > 0 => 'Partially paid',
            default => 'Sent',
        };
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->isGlobal() ? $q : $q->where('country_id', $user->countryId());
    }

    public function shareUrl(): ?string
    {
        return $this->share_token ? url('/s/i/'.$this->share_token) : null;
    }
}
