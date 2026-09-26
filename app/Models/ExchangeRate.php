<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = ['currency_code', 'rate_per_usd', 'effective_on', 'created_by'];

    protected $casts = ['rate_per_usd' => 'decimal:6', 'effective_on' => 'date'];
}
