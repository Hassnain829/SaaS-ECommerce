<?php

use App\Http\Controllers\Carrier\Connection\CarrierConnectionWizardController;
use App\Http\Controllers\Carrier\Connection\FedExIntegratorConnectionController;
use App\Http\Controllers\Carrier\Operations\FedExMerchantOperationsController;
use App\Http\Controllers\Settings\FedExShippingSettingsController;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FedEx merchant connection and settings routes
|--------------------------------------------------------------------------
|
| Loaded from carriers.php inside the authenticated store middleware group.
| No USPS controllers or routes belong here.
|
*/

Route::get('/settings/shipping/carriers/connect/fedex-integrator', [FedExIntegratorConnectionController::class, 'start'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.start');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/origin', [FedExIntegratorConnectionController::class, 'storeOrigin'])
    ->middleware(['store.permission:settings.manage', 'throttle:fedex-registration'])
    ->name('settings.shipping.fedex-integrator.origin');
Route::get('/settings/shipping/carriers/connect/fedex-integrator/{session}/eula', [FedExIntegratorConnectionController::class, 'showEula'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.eula');
Route::get('/settings/shipping/carriers/connect/fedex-integrator/{session}/eula/document', [FedExIntegratorConnectionController::class, 'showEulaDocument'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.eula.document');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/eula/scroll-complete', [FedExIntegratorConnectionController::class, 'markEulaScrollComplete'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.eula.scroll-complete');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/eula', [FedExIntegratorConnectionController::class, 'acceptEula'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.eula.accept');
Route::get('/settings/shipping/carriers/connect/fedex-integrator/{session}/account', [FedExIntegratorConnectionController::class, 'showAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.account');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/account', [FedExIntegratorConnectionController::class, 'submitAccount'])
    ->middleware(['store.permission:settings.manage', 'throttle:fedex-registration'])
    ->name('settings.shipping.fedex-integrator.account.submit');
Route::get('/settings/shipping/carriers/connect/fedex-integrator/{session}/mfa', [FedExIntegratorConnectionController::class, 'showMfa'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.mfa');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/mfa-method', [FedExIntegratorConnectionController::class, 'selectMfaMethod'])
    ->middleware(['store.permission:settings.manage', 'throttle:fedex-mfa-generation'])
    ->name('settings.shipping.fedex-integrator.mfa-method');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/verify-pin', [FedExIntegratorConnectionController::class, 'verifyPin'])
    ->middleware(['store.permission:settings.manage', 'throttle:fedex-mfa-validation'])
    ->name('settings.shipping.fedex-integrator.verify-pin');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/verify-invoice', [FedExIntegratorConnectionController::class, 'verifyInvoice'])
    ->middleware(['store.permission:settings.manage', 'throttle:fedex-mfa-validation'])
    ->name('settings.shipping.fedex-integrator.verify-invoice');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/resume', [FedExIntegratorConnectionController::class, 'resume'])
    ->middleware(['store.permission:settings.manage', 'throttle:fedex-connection-check'])
    ->name('settings.shipping.fedex-integrator.resume');
Route::get('/settings/shipping/carriers/connect/fedex-integrator/{session}/success', [FedExIntegratorConnectionController::class, 'success'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.success');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/cancel', [FedExIntegratorConnectionController::class, 'cancel'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.cancel');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/manage', [FedExIntegratorConnectionController::class, 'manage'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.manage');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/capabilities', [FedExIntegratorConnectionController::class, 'updateCapabilities'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.capabilities');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/verify', [FedExIntegratorConnectionController::class, 'verify'])
    ->middleware(['store.permission:settings.manage', 'throttle:fedex-connection-check'])
    ->name('settings.shipping.fedex-integrator.verify');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/reconnect', [FedExIntegratorConnectionController::class, 'reconnect'])
    ->middleware(['store.permission:settings.manage', 'throttle:fedex-registration'])
    ->name('settings.shipping.fedex-integrator.reconnect');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/disconnect', [FedExIntegratorConnectionController::class, 'disconnect'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.disconnect');

Route::post('/orders/{order}/fedex/validate-address', [FedExMerchantOperationsController::class, 'validateOrderAddress'])
    ->middleware(['store.permission:orders.manage', 'throttle:fedex-ops'])
    ->name('orders.fedex.validate-address');
Route::post('/orders/{order}/fedex/service-availability', [FedExMerchantOperationsController::class, 'checkOrderServiceAvailability'])
    ->middleware(['store.permission:orders.manage', 'throttle:fedex-ops'])
    ->name('orders.fedex.service-availability');
Route::post('/orders/{order}/fedex/rates', [FedExMerchantOperationsController::class, 'quoteOrderRates'])
    ->middleware(['store.permission:orders.manage', 'throttle:fedex-ops'])
    ->name('orders.fedex.rates');
Route::post('/orders/{order}/fedex/shipments', [FedExMerchantOperationsController::class, 'createOrderShipment'])
    ->middleware(['store.permission:orders.manage', 'throttle:fedex-ops'])
    ->name('orders.fedex.shipments.create');
Route::post('/orders/{order}/fedex/return-label', [FedExMerchantOperationsController::class, 'createReturnLabel'])
    ->middleware(['store.permission:orders.manage', 'throttle:fedex-ops'])
    ->name('orders.fedex.return-label');
Route::post('/orders/{order}/fedex/etd', [FedExMerchantOperationsController::class, 'uploadEtdDocument'])
    ->middleware(['store.permission:orders.manage', 'throttle:fedex-ops'])
    ->name('orders.fedex.etd.upload');
Route::post('/shipments/{shipment}/fedex/cancel', [FedExMerchantOperationsController::class, 'cancelShipment'])
    ->middleware(['store.permission:orders.manage', 'throttle:fedex-ops'])
    ->name('shipments.fedex.cancel');
Route::post('/shipments/{shipment}/fedex/tracking/refresh', [FedExMerchantOperationsController::class, 'refreshTracking'])
    ->middleware(['store.permission:orders.manage', 'throttle:fedex-ops'])
    ->name('shipments.fedex.tracking.refresh');
Route::get('/shipments/{shipment}/fedex/label', [FedExMerchantOperationsController::class, 'downloadLabel'])
    ->middleware(['store.permission:orders.manage', 'throttle:fedex-ops'])
    ->name('shipments.fedex.label.download');

if (app(FedExConfig::class)->modelBRoutesEnabled()) {
    Route::post('/settings/shipping/carriers/connect/fedex/details', [CarrierConnectionWizardController::class, 'storeFedExDetails'])
        ->middleware('store.permission:settings.manage')
        ->name('shipping.carriers.connect.fedex.details');
    Route::post('/settings/shipping/carrier-accounts/fedex', [FedExShippingSettingsController::class, 'storeFedExCarrierAccount'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.carrier-accounts.fedex.store');
    Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/registration', [FedExShippingSettingsController::class, 'updateFedExRegistrationSettings'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.carrier-accounts.fedex.registration.update');
    Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/test', [FedExShippingSettingsController::class, 'testFedExCarrierAccount'])
        ->middleware(['store.permission:settings.manage', 'throttle:fedex-connection-check'])
        ->name('settings.shipping.carrier-accounts.fedex.test');
}
