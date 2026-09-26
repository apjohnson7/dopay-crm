<?php

namespace App\Providers;

use App\Models\Country;
use App\Services\ApprovalService;
use App\Services\MessagingService;
use App\Support\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\ExchangeRateService::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::shouldBeStrict(false);
        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        // Super Administrators pass every permission check.
        Gate::before(fn ($user) => $user->hasRole('Super Administrator') ? true : null);

        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->uncompromised());

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }
            $view->with([
                'navFormsAwaiting' => app(ApprovalService::class)->awaiting($user)->count(),
                'navUnreadMessages' => app(MessagingService::class)->unreadCount($user),
                'navNotifications' => $user->unreadNotifications()->latest()->limit(12)->get(),
                'scopeCountries' => $user->isGlobal() ? Country::where('is_active', true)->orderBy('name')->get() : collect([$user->country()])->filter(),
                'scopeCountryId' => Scope::countryId(),
            ]);
        });
    }
}
