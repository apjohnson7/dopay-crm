<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    protected $fillable = ['customer_id', 'channel', 'kind', 'reference', 'recipient', 'body', 'status', 'provider_message_id', 'sent_by', 'sent_at', 'delivered_at', 'read_at', 'failure_reason', 'fallback_of', 'attempts'];

    protected $casts = ['sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
