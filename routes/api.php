<?php

use App\Http\Controllers\Api\DeveloperStorefrontCatalogController;
use App\Http\Controllers\Api\CatalogApiV1Controller;
use App\Http\Controllers\Api\ExternalOrderSyncController;
use App\Http\Controllers\Api\PlatformCheckoutController;
use App\Http\Controllers\Api\StripeConnectWebhookController;
use App\Http\Controllers\Api\StripeWebhookController;
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

Route::middleware(['dev.storefront.token'])
    ->prefix('v1/external')
    ->group(function (): void {
        Route::post('/orders', [ExternalOrderSyncController::class, 'store']);
    });

Route::middleware(['dev.storefront.token'])
    ->prefix('v1/checkout')
    ->group(function (): void {
        Route::post('/', [PlatformCheckoutController::class, 'store']);
        Route::get('/{checkout}', [PlatformCheckoutController::class, 'show']);
        Route::post('/{checkout}/confirm', [PlatformCheckoutController::class, 'confirm']);
    });

Route::post('/webhooks/stripe', StripeWebhookController::class);
Route::post('/webhooks/stripe/connect', StripeConnectWebhookController::class);
