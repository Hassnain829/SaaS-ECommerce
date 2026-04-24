<?php

use App\Http\Controllers\Api\DeveloperStorefrontCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Developer test storefront (Bearer token from dashboard)
|--------------------------------------------------------------------------
*/

Route::middleware(['dev.storefront.token'])
    ->prefix('developer-storefront')
    ->group(function (): void {
        Route::get('/catalog', [DeveloperStorefrontCatalogController::class, 'catalog']);
        Route::post('/orders', [DeveloperStorefrontCatalogController::class, 'placeOrder']);
    });
