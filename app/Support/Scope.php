<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Which country the signed-in user is looking at.
 * Local roles are always locked to their own country; global roles can pick one country or all.
 */
class Scope
{
    public static function countryId(): ?int
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        if (! $user->isGlobal()) {
            return $user->countryId();
        }
        $v = session('scope_country');

        return $v ? (int) $v : null;
    }

    public static function apply(Builder $query, string $column = 'country_id'): Builder
    {
        $id = self::countryId();

        return $id ? $query->where($column, $id) : $query;
    }
}
