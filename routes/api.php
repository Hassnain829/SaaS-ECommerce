<?php

use App\Http\Controllers\Api\CatalogApiV1Controller;
use App\Http\Controllers\Api\ConnectedSiteCatalogEventsController;
use App\Http\Controllers\Api\ConnectedSiteHealthController;
use App\Http\Controllers\Api\DeveloperStorefrontCatalogController;
use App\Http\Controllers\Api\PlatformCheckoutController;
use App\Http\Controllers\Api\StorefrontOrderController;
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

Route::middleware(['dev.storefront.token', 'throttle:api-dev-health'])
    ->match(['GET', 'POST'], 'v1/site/health', [ConnectedSiteHealthController::class, 'show']);

Route::middleware(['dev.storefront.token', 'throttle:api-dev-health'])
    ->get('v1/site/events/config', [ConnectedSiteCatalogEventsController::class, 'config']);

Route::middleware(['dev.storefront.token', 'throttle:api-dev-catalog'])
    ->prefix('v1/catalog')
    ->group(function (): void {
        Route::get('/products', [CatalogApiV1Controller::class, 'products']);
        Route::get('/products/{product}', [CatalogApiV1Controller::class, 'product']);
        Route::get('/categories', [CatalogApiV1Controller::class, 'categories']);
        Route::get('/brands', [CatalogApiV1Controller::class, 'brands']);
        Route::get('/attributes', [CatalogApiV1Controller::class, 'attributes']);
        Route::get('/events', [ConnectedSiteCatalogEventsController::class, 'index']);
    });

Route::middleware(['dev.storefront.token', 'throttle:api-dev-checkout'])
    ->prefix('v1/checkout')
    ->group(function (): void {
        Route::post('/', [PlatformCheckoutController::class, 'store']);
        Route::get('/{checkout}', [PlatformCheckoutController::class, 'show']);
        Route::post('/{checkout}/delivery-options', [PlatformCheckoutController::class, 'deliveryOptions']);
        Route::post('/{checkout}/shipping-method', [PlatformCheckoutController::class, 'selectShippingMethod']);
        Route::post('/{checkout}/shipping-address', [PlatformCheckoutController::class, 'updateShippingAddress']);
        Route::post('/{checkout}/items', [PlatformCheckoutController::class, 'updateItems']);
        Route::post('/{checkout}/coupon', [PlatformCheckoutController::class, 'applyCoupon']);
        Route::delete('/{checkout}/coupon', [PlatformCheckoutController::class, 'removeCoupon']);
        Route::post('/{checkout}/confirm', [PlatformCheckoutController::class, 'confirm']);
    });

Route::middleware(['dev.storefront.token', 'throttle:api-dev-checkout'])
    ->get('v1/orders/confirmation/{token}', [StorefrontOrderController::class, 'confirmation']);

Route::post('/webhooks/stripe/{mode}', StripeWebhookController::class)->where('mode', 'test|live');
Route::post('/webhooks/stripe', StripeWebhookController::class)->defaults('mode', 'test');
Route::post('/webhooks/stripe/connect/{mode}', StripeConnectWebhookController::class)->where('mode', 'test|live');
Route::post('/webhooks/stripe/connect', StripeConnectWebhookController::class)->defaults('mode', 'test');
