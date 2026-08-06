<?php

use App\Http\Controllers\Carrier\Connection\CarrierConnectionWizardController;
use App\Http\Controllers\Settings\ShippingSettingsController;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shared carrier connection loader
|--------------------------------------------------------------------------
|
| FedEx merchant routes: routes/fedex.php
| USPS merchant routes: routes/usps.php
| FedEx validation / sandbox developer tools: routes/fedex-validation.php
| (loaded only when FedExConfig::validationRoutesEnabled() is true)
|
| Specific carrier connect paths must register before connect/{carrier}.
|
*/

Route::get('/settings/shipping/carriers/connect', [CarrierConnectionWizardController::class, 'index'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.index');

require __DIR__.'/fedex.php';
require __DIR__.'/usps.php';

if (app(FedExConfig::class)->validationRoutesEnabled()) {
    require __DIR__.'/fedex-validation.php';
}

Route::get('/settings/shipping/carriers/connect/{carrier}', [CarrierConnectionWizardController::class, 'show'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.show');
Route::post('/settings/shipping/carriers/connect/{carrier}/origin', [CarrierConnectionWizardController::class, 'storeOrigin'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.origin');
Route::post('/settings/shipping/carriers/connect/{carrier}/ownership', [CarrierConnectionWizardController::class, 'storeOwnership'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.ownership');
Route::post('/settings/shipping/carriers/connect/{carrier}/test', [CarrierConnectionWizardController::class, 'test'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.test');

Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/disable', [ShippingSettingsController::class, 'disableCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.disable');
Route::post('/settings/shipping/carrier-accounts', [ShippingSettingsController::class, 'storeCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.store');
Route::patch('/settings/shipping/carrier-accounts/{carrierAccount}', [ShippingSettingsController::class, 'updateCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.update');
Route::delete('/settings/shipping/carrier-accounts/{carrierAccount}', [ShippingSettingsController::class, 'destroyCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.destroy');
