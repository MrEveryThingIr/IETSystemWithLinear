<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('invitation-acceptance', function (Request $request): Limit {
            $identity = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return Limit::perMinute(10)->by('invitation-acceptance:'.$identity);
        });

        Livewire::addPersistentMiddleware([
            RedirectIfAuthenticated::class,
            EnsureEmailIsVerified::class,
            EnsureAccountIsActive::class,
        ]);
    }
}
