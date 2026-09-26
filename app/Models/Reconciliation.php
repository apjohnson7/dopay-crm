<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reconciliation extends Model
{
    public $timestamps = false;

    protected $fillable = ['ledger_account_id', 'period', 'confirmed_by', 'confirmed_at'];

    protected $casts = ['confirmed_at' => 'datetime'];

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
