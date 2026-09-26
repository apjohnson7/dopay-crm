<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = ['iso2', 'name', 'doc_code', 'legal_entity', 'address', 'phone', 'email', 'tax_id', 'bank_details', 'mobile_money', 'currency_code', 'tax_name', 'tax_rate', 'vat_filing_day', 'timezone', 'timezone_label', 'is_active'];

    protected $casts = ['tax_rate' => 'decimal:3', 'vat_filing_day' => 'integer', 'is_active' => 'boolean'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** Current local time in this country, e.g. "09:42 EAT". */
    public function localTime(?\DateTimeInterface $at = null): string
    {
        $t = \Carbon\Carbon::parse($at ?? now())->setTimezone($this->timezone);

        return $t->format('H:i').' '.$this->timezone_label;
    }
}
