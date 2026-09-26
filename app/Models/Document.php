<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    protected $fillable = ['name', 'disk', 'path', 'mime', 'size', 'category', 'tags', 'expires_on', 'version', 'access', 'documentable_type', 'documentable_id', 'uploaded_by'];

    protected $casts = ['tags' => 'array', 'expires_on' => 'date'];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
