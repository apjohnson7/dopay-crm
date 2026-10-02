<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer's attempt to pay an invoice online. Becomes a Payment only once the provider confirms it. */
class PaymentIntent extends Model
{
    protected $fillable = ['uuid', 'invoice_id', 'payment_gateway_id', 'amount', 'currency_code', 'phone', 'status', 'provider_reference', 'checkout_url', 'payment_id', 'started_from', 'failure_reason'];

    protected $casts = ['amount' => 'decimal:2'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
