<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['code', 'company', 'contact_person', 'phone', 'email', 'address', 'country_id', 'tax_id', 'bank_details', 'payment_terms_days', 'supplies', 'currency_code'];

    protected $casts = ['bank_details' => 'encrypted'];

    protected array $auditExclude = ['bank_details'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
