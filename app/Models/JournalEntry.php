<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A posted journal entry. Entries are never edited or deleted; mistakes are corrected with a reversing entry. */
class JournalEntry extends Model
{
    protected $fillable = ['country_id', 'number', 'entry_date', 'memo', 'kind', 'source_key', 'source_type', 'source_id', 'posted_by', 'authorized_by', 'reason'];

    protected $casts = ['entry_date' => 'date'];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function total(): float
    {
        return (float) $this->lines->sum('debit');
    }
}
