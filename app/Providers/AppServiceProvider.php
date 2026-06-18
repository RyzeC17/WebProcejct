<?php

namespace App\Providers;

use App\Auth\DjangoPasswordHasher;
use App\Auth\DjangoUserProvider;
use Illuminate\Support\Facades\Auth;
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
        Auth::provider('django', function ($app, array $config) {
            return new DjangoUserProvider(
                $app['hash'],
                $config['model'],
                new DjangoPasswordHasher(),
            );
        });
    }
}
