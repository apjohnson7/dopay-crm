<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** What the tax authority returned for an invoice or credit note: its number, verification code and QR payload. */
class FiscalDocument extends Model
{
    protected $fillable = ['document_type', 'document_id', 'country_id', 'system', 'status', 'test', 'authority_number', 'verification_code', 'qr_payload', 'message', 'attempts', 'request', 'response', 'submitted_at'];

    protected $casts = ['test' => 'boolean', 'request' => 'array', 'response' => 'array', 'submitted_at' => 'datetime'];

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function displayStatus(): string
    {
        return ['accepted' => 'Accepted', 'rejected' => 'Rejected', 'pending' => 'Pending', 'not_submitted' => 'Not submitted'][$this->status] ?? ucfirst($this->status);
    }
}
