<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A one-time portal sign-in link for a customer; the one-time code is only ever stored hashed. */
class PortalLogin extends Model
{
    protected $fillable = ['customer_id', 'token_hash', 'channel', 'code_hash', 'code_expires_at', 'attempts', 'expires_at', 'used_at', 'created_by'];

    protected $hidden = ['token_hash', 'code_hash'];

    protected $casts = ['code_expires_at' => 'datetime', 'expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
