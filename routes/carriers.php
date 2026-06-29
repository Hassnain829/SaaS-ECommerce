<?php

use App\Http\Controllers\Carrier\Connection\CarrierConnectionWizardController;
use App\Http\Controllers\Carrier\Connection\FedExIntegratorConnectionController;
use App\Http\Controllers\Carrier\Operations\FedExCarrierTestController;
use App\Http\Controllers\Carrier\Validation\FedExValidationArtifactController;
use App\Http\Controllers\Carrier\Validation\FedExValidationExportController;
use App\Http\Controllers\Carrier\Validation\FedExValidationRunController;
use App\Http\Controllers\Carrier\Validation\FedExValidationWorkspaceController;
use App\Http\Controllers\ShippingSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Carrier connection, operations, and validation routes
|--------------------------------------------------------------------------
|
| Extracted from web.php during CLEAN-2 for discoverability.
| URLs, route names, middleware, and controller actions are unchanged.
|
*/

Route::get('/settings/shipping/carriers/connect', [CarrierConnectionWizardController::class, 'index'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.index');
Route::post('/settings/shipping/carriers/connect/fedex/details', [CarrierConnectionWizardController::class, 'storeFedExDetails'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.fedex.details');
Route::get('/settings/shipping/carriers/connect/fedex-integrator', [FedExIntegratorConnectionController::class, 'start'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.start');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/origin', [FedExIntegratorConnectionController::class, 'storeOrigin'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.origin');
Route::get('/settings/shipping/carriers/connect/fedex-integrator/{session}/eula', [FedExIntegratorConnectionController::class, 'showEula'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.eula');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/eula', [FedExIntegratorConnectionController::class, 'acceptEula'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.eula.accept');
Route::get('/settings/shipping/carriers/connect/fedex-integrator/{session}/account', [FedExIntegratorConnectionController::class, 'showAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.account');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/account', [FedExIntegratorConnectionController::class, 'submitAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.account.submit');
Route::get('/settings/shipping/carriers/connect/fedex-integrator/{session}/mfa', [FedExIntegratorConnectionController::class, 'showMfa'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.mfa');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/mfa-method', [FedExIntegratorConnectionController::class, 'selectMfaMethod'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.mfa-method');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/verify-pin', [FedExIntegratorConnectionController::class, 'verifyPin'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.verify-pin');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/verify-invoice', [FedExIntegratorConnectionController::class, 'verifyInvoice'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.verify-invoice');
Route::get('/settings/shipping/carriers/connect/fedex-integrator/{session}/success', [FedExIntegratorConnectionController::class, 'success'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.success');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/{session}/cancel', [FedExIntegratorConnectionController::class, 'cancel'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.fedex-integrator.cancel');
Route::get('/settings/shipping/carriers/connect/{carrier}', [CarrierConnectionWizardController::class, 'show'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.show');
Route::post('/settings/shipping/carriers/connect/{carrier}/origin', [CarrierConnectionWizardController::class, 'storeOrigin'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.origin');
Route::post('/settings/shipping/carriers/connect/{carrier}/ownership', [CarrierConnectionWizardController::class, 'storeOwnership'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.ownership');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation-export', [FedExIntegratorConnectionController::class, 'exportValidation'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation-export');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation', [FedExValidationWorkspaceController::class, 'show'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/export/diagnostic', [FedExValidationExportController::class, 'diagnostic'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.export.diagnostic');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/export/final', [FedExValidationExportController::class, 'final'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.export.final');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/documents', [FedExValidationArtifactController::class, 'uploadDocument'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.documents.upload');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/printed-scans', [FedExValidationArtifactController::class, 'uploadPrintedScan'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.scans.upload');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/tracking-screenshot', [FedExValidationArtifactController::class, 'uploadTrackingScreenshot'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.tracking-screenshot.upload');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/authorization', [FedExValidationRunController::class, 'runAuthorizationEvidence'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.authorization');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/sweden-passthrough', [FedExValidationRunController::class, 'runSwedenPassthrough'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.sweden-passthrough');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/sweden-screenshots', [FedExValidationArtifactController::class, 'uploadSwedenScreenshots'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.sweden-screenshots.upload');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/address', [FedExValidationRunController::class, 'runAddressValidation'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.address');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/service-availability', [FedExValidationRunController::class, 'runServiceAvailability'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.service-availability');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/rate', [FedExValidationRunController::class, 'runRateQuote'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.rate');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/mfa/invoice', [FedExValidationRunController::class, 'runInvoiceValidation'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.mfa.invoice');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/ship/{testCaseKey}', [FedExValidationRunController::class, 'runLockedShipLabel'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.ship');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/tracking', [FedExValidationRunController::class, 'runTracking'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.tracking');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/cancel', [FedExValidationRunController::class, 'runShipCancel'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.cancel');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/trade-documents', [FedExValidationRunController::class, 'runTradeDocuments'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.trade-documents');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/artifacts/{artifact}/download', [FedExValidationArtifactController::class, 'download'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.artifacts.download');
Route::post('/settings/shipping/carriers/connect/{carrier}/test', [CarrierConnectionWizardController::class, 'test'])
    ->middleware('store.permission:settings.manage')
    ->name('shipping.carriers.connect.test');
Route::post('/settings/shipping/carrier-accounts/fedex', [ShippingSettingsController::class, 'storeFedExCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.store');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/registration', [ShippingSettingsController::class, 'updateFedExRegistrationSettings'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.registration.update');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/debug-payload', [ShippingSettingsController::class, 'exportFedExDebugPayload'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.debug-payload');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/test', [ShippingSettingsController::class, 'testFedExCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.test');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/test-address', [FedExCarrierTestController::class, 'testAddressValidation'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.test-address');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/test-service-availability', [FedExCarrierTestController::class, 'testServiceAvailability'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.test-service-availability');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/test-rate-quote', [FedExCarrierTestController::class, 'testRateQuote'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.test-rate-quote');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/test-ship-validate', [FedExCarrierTestController::class, 'testShipValidate'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.test-ship-validate');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/test-ship-label', [FedExCarrierTestController::class, 'testShipLabel'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.test-ship-label');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/test-tracking', [FedExCarrierTestController::class, 'testTracking'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.test-tracking');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/cancel-test-shipment', [FedExCarrierTestController::class, 'cancelTestShipment'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.cancel-test-shipment');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/sandbox-platform-fallback', [ShippingSettingsController::class, 'enableFedExSandboxPlatformFallback'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.sandbox-platform-fallback');
Route::post('/settings/shipping/carrier-accounts/usps', [ShippingSettingsController::class, 'storeUspsCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.usps.store');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/test', [ShippingSettingsController::class, 'testUspsCarrierAccount'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.usps.test');
Route::post('/settings/shipping/usps/test-package-quote', [ShippingSettingsController::class, 'storeUspsTestPackage'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps.test-package-quote');
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
