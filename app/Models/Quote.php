<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasLineItems;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A quotation, or (kind = order) the sales order created when a quote is accepted. */
class Quote extends Model
{
    use Auditable, HasLineItems;

    /** The access token opens the quote without signing in, so it stays out of the audit trail. */
    protected array $auditExclude = ['access_token'];

    protected $fillable = ['number', 'kind', 'customer_id', 'country_id', 'branch_id', 'currency_code', 'exchange_rate', 'issue_date', 'valid_until', 'status', 'subtotal', 'discount_total', 'tax_total', 'total', 'notes', 'terms', 'customer_reference', 'access_token', 'sent_at', 'accepted_at', 'accepted_by_name', 'accepted_by', 'decline_reason', 'quote_id', 'invoice_id', 'version', 'created_by'];

    protected $casts = [
        'issue_date' => 'date', 'valid_until' => 'date', 'sent_at' => 'datetime', 'accepted_at' => 'datetime',
        'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'exchange_rate' => 'decimal:6',
    ];

    public function isOrder(): bool
    {
        return $this->kind === 'order';
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
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

    /** For a sales order: the quote it came from. */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    /** For a quote: the sales order made from it. */
    public function order(): HasOne
    {
        return $this->hasOne(Quote::class, 'quote_id')->where('kind', 'order');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Status as the business sees it: a sent quote past its validity date is Expired. */
    public function displayStatus(): string
    {
        if ($this->kind === 'quote' && $this->status === 'sent' && $this->valid_until && $this->valid_until->isBefore(today())) {
            return 'Expired';
        }

        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function shareUrl(): ?string
    {
        return $this->access_token ? url('/q/'.$this->access_token) : null;
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->isGlobal() ? $q : $q->where('country_id', $user->countryId());
    }
}
