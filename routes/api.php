<?php

use App\Http\Controllers\Api\DeveloperStorefrontCatalogController;
use App\Http\Controllers\Api\CatalogApiV1Controller;
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

Route::middleware(['dev.storefront.token'])
    ->prefix('v1/catalog')
    ->group(function (): void {
        Route::get('/products', [CatalogApiV1Controller::class, 'products']);
        Route::get('/products/{product}', [CatalogApiV1Controller::class, 'product']);
        Route::get('/categories', [CatalogApiV1Controller::class, 'categories']);
        Route::get('/brands', [CatalogApiV1Controller::class, 'brands']);
        Route::get('/attributes', [CatalogApiV1Controller::class, 'attributes']);
    });
