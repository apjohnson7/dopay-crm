<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && config('dopay.require_two_factor') && ! $user->two_factor_confirmed_at) {
            return redirect()->route('profile.security')->with('status', 'Set up two-factor authentication to continue. It is required for all Dopay users.');
        }
        if ($user && ! $user->signing_pin && ! $request->routeIs('profile.*')) {
            return redirect()->route('profile.security')->with('status', 'Choose a signing PIN. You use it to sign finance forms and authorize sensitive actions.');
        }

        return $next($request);
    }
}
