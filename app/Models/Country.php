<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = ['iso2', 'name', 'doc_code', 'legal_entity', 'address', 'phone', 'email', 'tax_id', 'bank_details', 'mobile_money', 'currency_code', 'tax_name', 'tax_rate', 'vat_filing_day', 'wht_rate', 'einvoice_mode', 'einvoice_credentials', 'einvoice_connected_at', 'default_language', 'books_closed_through', 'timezone', 'timezone_label', 'is_active'];

    protected $hidden = ['einvoice_credentials'];

    protected $casts = ['einvoice_credentials' => 'encrypted:array', 'einvoice_connected_at' => 'datetime', 'wht_rate' => 'decimal:2', 'tax_rate' => 'decimal:3', 'vat_filing_day' => 'integer', 'books_closed_through' => 'string', 'is_active' => 'boolean'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function gateways(): HasMany
    {
        return $this->hasMany(PaymentGateway::class);
    }

    /** Label for the taxpayer number on documents: NIU in Cameroon, NCC in Ivory Coast, TIN elsewhere. */
    public function taxIdLabel(): string
    {
        return config('einvoicing.tax_id_labels.'.$this->iso2, 'TIN');
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
