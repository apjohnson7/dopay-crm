<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FinanceForm extends Model
{
    use Auditable;

    public const STATUS_LABELS = [
        'draft' => 'Draft', 'in_approval' => 'In approval', 'returned' => 'Returned', 'approved' => 'Approved', 'paid' => 'Paid',
        'reimbursed' => 'Reimbursed', 'awaiting_liquidation' => 'Awaiting liquidation', 'liquidated' => 'Liquidated', 'settled' => 'Settled', 'void' => 'Void',
    ];

    protected $fillable = ['type', 'reference', 'country_id', 'branch_id', 'currency_code', 'exchange_rate', 'status', 'data', 'total', 'version', 'prepared_by', 'submitted_at', 'approved_at', 'return_note', 'related_form_id'];

    protected $casts = ['data' => 'array', 'total' => 'decimal:2', 'exchange_rate' => 'decimal:6', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];

    public function definition(): array
    {
        return config('dopay.forms.'.$this->type);
    }

    public function name(): string
    {
        return $this->definition()['name'];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinanceFormLine::class)->orderBy('position');
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(FormSignature::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Signatures for the current version, keyed by step index (-1 = requester). */
    public function currentSignatures()
    {
        return $this->signatures->where('version', $this->version)->keyBy('step_index');
    }

    public function statusLabel(): string
    {
        if ($this->type === 'K' && $this->status === 'awaiting_liquidation' && isset($this->data['liquidation_date']) && $this->data['liquidation_date'] < today()->toDateString()) {
            return 'Overdue';
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function datum(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isGlobal()) {
            return $q;
        }

        // Everyone sees their own country's forms, plus interbranch memos their country is paying for.
        // Group approvers also see forms from other countries once those are submitted into approval (and afterwards).
        return $q->where(fn ($w) => $w->where('country_id', $user->countryId())
            ->orWhere(fn ($j) => $j->where('type', 'J')->where('data->paying_country_id', $user->countryId()))
            ->when($user->isGroupApprover(), fn ($g) => $g->orWhereNotIn('status', ['draft', 'returned', 'void'])));
    }
}
