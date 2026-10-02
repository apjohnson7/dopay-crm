<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Every notification a payment provider sends, kept with what DoPay did with it. */
class WebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'event', 'reference', 'payment_gateway_id', 'signature_valid', 'result', 'note', 'payload'];

    protected $casts = ['signature_valid' => 'boolean', 'payload' => 'array'];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }
}
