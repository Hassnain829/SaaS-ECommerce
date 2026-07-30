<?php

namespace App\Providers;

use App\Models\Store;
use App\Services\Notifications\NotificationQueryService;
use App\Support\Catalog\ProductImportQueue;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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

        View::composer('components.ui.merchant-topbar', function ($view): void {
            $count = 0;
            $request = request();
            $user = $request->user();
            $store = $request->attributes->get('currentStore');

            if (
                $user
                && $store instanceof Store
                && Schema::hasTable('notifications')
            ) {
                $count = app(NotificationQueryService::class)->unreadCount($store, $user);
            }

            $view->with('merchantUnreadNotificationCount', $count);
        });
    }
}
