<?php

namespace App\Providers;

use App\Services\CommerceAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        foreach (['restock-subscribe' => 6, 'restock-view' => 30, 'restock-manage' => 10] as $name => $limit) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($limit)->by($name.':'.$request->ip()));
        }
        Gate::define('manage-commerce', [CommerceAccess::class, 'allowed']);
        Gate::define('verify-payments', [CommerceAccess::class, 'allowed']);
    }
}
