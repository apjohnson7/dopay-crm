<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    protected $fillable = ['country_id', 'code', 'name', 'type', 'role', 'statement_line', 'is_system', 'is_active'];

    protected $casts = ['is_system' => 'boolean', 'is_active' => 'boolean'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** Debit-normal accounts show debit balances as positive; the others show credit balances as positive. */
    public function natural(float $debitMinusCredit): float
    {
        return in_array($this->type, ['asset', 'expense'], true) ? $debitMinusCredit : -$debitMinusCredit;
    }

    public function label(): string
    {
        return $this->code.' '.$this->name;
    }
}
