<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom middleware
        $this->app['router']->aliasMiddleware('permission', \App\Http\Middleware\CheckPermission::class);

        // Saat user yang sudah login membuka halaman guest (mis. /login),
        // arahkan ke halaman pertama sesuai permission-nya, bukan dashboard.
        RedirectIfAuthenticated::redirectUsing(function ($request) {
            return \App\Http\Controllers\AuthController::homePath() ?? url('/dashboard');
        });
    }
}
