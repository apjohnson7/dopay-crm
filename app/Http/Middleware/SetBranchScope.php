<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Local roles cannot keep a scope pointing at another country (e.g. after a role change). */
class SetBranchScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->isGlobal() && session('scope_country') && (int) session('scope_country') !== Auth::user()->countryId()) {
            session()->forget('scope_country');
        }

        return $next($request);
    }
}
