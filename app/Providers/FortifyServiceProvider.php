<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        Fortify::authenticateUsing(function (Request $request) {
            $email = Str::lower((string) $request->input('email'));
            $user = User::where('email', $email)->first();
            if ($user && $user->is_active && Hash::check((string) $request->input('password'), $user->password)) {
                $user->forceFill(['last_login_at' => now()])->save();
                AuditLogger::log('Signed in', $user, null, null, $user->email);

                return $user;
            }
            // Record failures (without the password) so repeated attempts show up in the audit trail.
            AuditLogger::log('Failed sign-in', $user, null, ['ip' => $request->ip()], $email);
        });

        // The same message whether or not the email exists, so the reset form can't be used to discover accounts.
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, fn () => new class implements FailedPasswordResetLinkRequestResponse {
            public function toResponse($request)
            {
                return back()->with('status', 'If that email belongs to a Dopay account, a reset link is on its way.');
            }
        });
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, fn () => new class implements SuccessfulPasswordResetLinkRequestResponse {
            public function toResponse($request)
            {
                return back()->with('status', 'If that email belongs to a Dopay account, a reset link is on its way.');
            }
        });

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input(Fortify::username()));

            // 5 a minute per email and address, and at most 20 an hour per account from anywhere (slows password spraying across IPs).
            return [
                Limit::perMinute(5)->by(Str::transliterate($email.'|'.$request->ip())),
                Limit::perHour(20)->by('login-account:'.$email),
            ];
        });
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
