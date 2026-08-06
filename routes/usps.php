<?php

use App\Http\Controllers\Carrier\Connection\USPSMerchantConnectionController;
use App\Http\Controllers\Settings\USPSShippingSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| USPS merchant connection and settings routes
|--------------------------------------------------------------------------
|
| Loaded from carriers.php inside the authenticated store middleware group.
| No FedEx controllers or routes belong here.
|
*/

Route::get('/settings/shipping/carriers/connect/usps-merchant', [USPSMerchantConnectionController::class, 'start'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.start');
Route::post('/settings/shipping/carriers/connect/usps-merchant/origin', [USPSMerchantConnectionController::class, 'storeOrigin'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.origin');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/usps/wizard/{step}', [USPSMerchantConnectionController::class, 'showWizard'])
    ->middleware('store.permission:settings.manage')
    ->where('step', 'requirements|origin|identifiers|authorization')
    ->name('settings.shipping.usps-merchant.wizard');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/origin', [USPSMerchantConnectionController::class, 'updateOrigin'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.origin.update');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/identifiers', [USPSMerchantConnectionController::class, 'storeIdentifiers'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.identifiers');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/authorization', [USPSMerchantConnectionController::class, 'storeAuthorizationAcknowledgement'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.authorization');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/usps/oauth/start', [USPSMerchantConnectionController::class, 'startOAuth'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.oauth.start');
Route::get('/settings/shipping/carriers/usps/oauth/callback', [USPSMerchantConnectionController::class, 'oauthCallback'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.oauth.callback');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/verify', [USPSMerchantConnectionController::class, 'verifyConnection'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.verify');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/verify-ship-suite', [USPSMerchantConnectionController::class, 'verifyShipSuite'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.verify-ship-suite');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/reauthorize', [USPSMerchantConnectionController::class, 'reauthorize'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.reauthorize');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/usps/manage', [USPSMerchantConnectionController::class, 'manage'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.manage');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/disconnect', [USPSMerchantConnectionController::class, 'disconnect'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.disconnect');

Route::post('/settings/shipping/carrier-accounts/usps', [USPSShippingSettingsController::class, 'storeUspsCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.usps.store');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/test', [USPSShippingSettingsController::class, 'testUspsCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.usps.test');
Route::post('/settings/shipping/usps/test-package-quote', [USPSShippingSettingsController::class, 'storeUspsTestPackage'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps.test-package-quote');
