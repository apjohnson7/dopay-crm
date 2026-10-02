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

    protected $fillable = ['number', 'customer_id', 'country_id', 'branch_id', 'currency_code', 'exchange_rate', 'amount', 'method', 'reference', 'paid_on', 'status', 'payment_gateway_id', 'fee', 'received_by', 'reversed_by', 'reversal_reason'];

    protected $casts = ['paid_on' => 'date', 'amount' => 'decimal:2', 'fee' => 'decimal:2', 'exchange_rate' => 'decimal:6'];

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

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    /** Who received it: a staff member, or the provider for online payments. */
    public function receivedByLabel(): string
    {
        return $this->receiver?->name ?? ($this->gateway ? $this->gateway->label().' (online)' : 'DoPay');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
