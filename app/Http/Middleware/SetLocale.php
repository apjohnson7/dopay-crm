<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Screens follow the signed-in user's language (English or French). Staff in Cameroon and Ivory Coast default to
 * French through their country's default language. Documents and the customer portal follow the customer's language instead.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $locale = $user->locale ?: ($user->branch?->country?->default_language ?: config('app.locale', 'en'));
            if (in_array($locale, ['en', 'fr'], true)) {
                app()->setLocale($locale);
            }
        }

        return $next($request);
    }
}
