<?php

namespace App\Providers;

use App\Support\Catalog\ProductImportQueue;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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
        Config::set('product_import.queue_connection', ProductImportQueue::connection());

        RateLimiter::for('api-dev-catalog', function (Request $request): Limit {
            $id = $request->attributes->get('developerStorefrontStore')?->id;

            return Limit::perMinute(180)->by($id ? 'store:'.$id : $request->ip());
        });

        RateLimiter::for('api-dev-orders', function (Request $request): Limit {
            $id = $request->attributes->get('developerStorefrontStore')?->id;

            return Limit::perMinute(45)->by($id ? 'store:'.$id : $request->ip());
        });

        RateLimiter::for('api-dev-checkout', function (Request $request): Limit {
            $id = $request->attributes->get('developerStorefrontStore')?->id;

            return Limit::perMinute(90)->by($id ? 'store:'.$id : $request->ip());
        });

        RateLimiter::for('api-dev-external', function (Request $request): Limit {
            $id = $request->attributes->get('developerStorefrontStore')?->id;

            return Limit::perMinute(45)->by($id ? 'store:'.$id : $request->ip());
        });
    }
}
