<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
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
        Livewire::addPersistentMiddleware([
            RedirectIfAuthenticated::class,
            EnsureEmailIsVerified::class,
            EnsureAccountIsActive::class,
        ]);
    }
}
