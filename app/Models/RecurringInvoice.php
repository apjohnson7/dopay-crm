<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A schedule that raises the same invoice every month, quarter or year once it has been approved. */
class RecurringInvoice extends Model
{
    use Auditable;

    public const FREQUENCIES = [1 => 'Monthly', 3 => 'Every 3 months', 6 => 'Every 6 months', 12 => 'Yearly'];

    protected $fillable = ['code', 'name', 'customer_id', 'country_id', 'branch_id', 'currency_code', 'every_months', 'next_run_on', 'ends_on', 'last_run_on', 'send_channel', 'status', 'notes', 'created_by', 'approved_by', 'approved_at'];

    protected $casts = ['next_run_on' => 'date', 'ends_on' => 'date', 'last_run_on' => 'date', 'approved_at' => 'datetime', 'every_months' => 'integer'];

    public function items(): HasMany
    {
        return $this->hasMany(RecurringInvoiceItem::class)->orderBy('position');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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

    public function frequencyLabel(): string
    {
        return self::FREQUENCIES[$this->every_months] ?? 'Every '.$this->every_months.' months';
    }

    /** What one invoice from this schedule comes to, tax included. */
    public function amountPerInvoice(): float
    {
        return round($this->items->sum(function ($i) {
            $net = (float) $i->quantity * (float) $i->unit_price * (1 - (float) $i->discount_pct / 100);

            return $net * (1 + (float) $i->tax_pct / 100);
        }), 2);
    }

    public function isDue(?\Carbon\CarbonInterface $on = null): bool
    {
        return $this->status === 'active' && $this->next_run_on->lte($on ?? today());
    }
}
