<?php

use App\Http\Controllers\Api\DeveloperStorefrontCatalogController;
use App\Http\Controllers\Api\CatalogApiV1Controller;
use App\Http\Controllers\Api\ExternalOrderSyncController;
use App\Http\Controllers\Api\ExternalShipmentSyncController;
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
        Route::middleware('throttle:api-dev-catalog')
            ->get('catalog', [DeveloperStorefrontCatalogController::class, 'catalog']);
        Route::middleware('throttle:api-dev-orders')
            ->post('orders', [DeveloperStorefrontCatalogController::class, 'placeOrder']);
    });

Route::middleware(['dev.storefront.token', 'throttle:api-dev-catalog'])
    ->prefix('v1/catalog')
    ->group(function (): void {
        Route::get('/products', [CatalogApiV1Controller::class, 'products']);
        Route::get('/products/{product}', [CatalogApiV1Controller::class, 'product']);
        Route::get('/categories', [CatalogApiV1Controller::class, 'categories']);
        Route::get('/brands', [CatalogApiV1Controller::class, 'brands']);
        Route::get('/attributes', [CatalogApiV1Controller::class, 'attributes']);
    });

Route::middleware(['dev.storefront.token', 'throttle:api-dev-external'])
    ->prefix('v1/external')
    ->group(function (): void {
        Route::post('/orders', [ExternalOrderSyncController::class, 'store']);
        Route::post('/shipments', [ExternalShipmentSyncController::class, 'store']);
    });

Route::middleware(['dev.storefront.token', 'throttle:api-dev-checkout'])
    ->prefix('v1/checkout')
    ->group(function (): void {
        Route::post('/', [PlatformCheckoutController::class, 'store']);
        Route::get('/{checkout}', [PlatformCheckoutController::class, 'show']);
        Route::post('/{checkout}/delivery-options', [PlatformCheckoutController::class, 'deliveryOptions']);
        Route::post('/{checkout}/shipping-method', [PlatformCheckoutController::class, 'selectShippingMethod']);
        Route::post('/{checkout}/confirm', [PlatformCheckoutController::class, 'confirm']);
    });

Route::post('/webhooks/stripe', StripeWebhookController::class);
Route::post('/webhooks/stripe/connect', StripeConnectWebhookController::class);
