<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = ['number', 'payment_id', 'issued_on', 'status'];

    protected $casts = ['issued_on' => 'date'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
