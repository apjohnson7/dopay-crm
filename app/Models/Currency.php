<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['code', 'name', 'decimals'];

    /** Currencies shown without decimals on documents. */
    public static function decimalsFor(string $code): int
    {
        return in_array($code, ['UGX', 'XAF', 'XOF', 'RWF', 'BIF', 'KES_NO_DECIMALS'], true) ? 0 : 2;
    }
}
