<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderRule extends Model
{
    protected $fillable = ['label', 'offset_days', 'channel', 'document_template_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
