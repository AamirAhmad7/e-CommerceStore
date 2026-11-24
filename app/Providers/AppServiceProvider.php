<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Services\CommerceAccess;
use App\Support\ProductWorkspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
        foreach ([Product::class, ProductColor::class, ProductVariant::class, Media::class] as $model) {
            $model::saved(fn () => ProductWorkspace::forget());
            $model::deleted(fn () => ProductWorkspace::forget());
        }
        Gate::define('manage-commerce', [CommerceAccess::class, 'allowed']);
        Gate::define('verify-payments', [CommerceAccess::class, 'owner']);
    }
}
