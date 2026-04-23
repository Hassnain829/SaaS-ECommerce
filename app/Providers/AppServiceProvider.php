<?php

namespace App\Providers;

use App\Support\Catalog\ProductImportQueue;
use Illuminate\Support\Facades\Config;
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
    }
}
