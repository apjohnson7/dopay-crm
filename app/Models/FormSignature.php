<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSignature extends Model
{
    protected $fillable = ['finance_form_id', 'version', 'step_index', 'step_label', 'user_id', 'signer_role', 'signed_at', 'local_time', 'code', 'comment', 'ip_address', 'user_agent'];

    protected $casts = ['signed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
