<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use Auditable;

    protected $fillable = ['number', 'customer_id', 'country_id', 'branch_id', 'currency_code', 'exchange_rate', 'amount', 'method', 'reference', 'paid_on', 'status', 'received_by', 'reversed_by', 'reversal_reason'];

    protected $casts = ['paid_on' => 'date', 'amount' => 'decimal:2', 'exchange_rate' => 'decimal:6'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
