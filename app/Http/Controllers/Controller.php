<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function ensureCountry(int $countryId): void
    {
        abort_unless(auth()->user()->canActForCountry($countryId), 403, 'This record belongs to another country.');
    }
}
