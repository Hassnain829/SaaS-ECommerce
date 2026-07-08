<?php

use App\Http\Controllers\Carrier\Connection\CarrierConnectionWizardController;
use App\Http\Controllers\Carrier\Connection\FedExIntegratorConnectionController;
use App\Http\Controllers\Carrier\Connection\USPSMerchantConnectionController;
use App\Http\Controllers\Carrier\Operations\FedExCarrierTestController;
use App\Http\Controllers\Carrier\Validation\FedExValidationArtifactController;
use App\Http\Controllers\Carrier\Validation\FedExValidationCapabilitiesController;
use App\Http\Controllers\Carrier\Validation\FedExValidationExportController;
use App\Http\Controllers\Carrier\Validation\FedExValidationFinalSubmissionController;
use App\Http\Controllers\Carrier\Validation\FedExValidationRunController;
use App\Http\Controllers\Carrier\Validation\FedExValidationWorkspaceController;
use App\Http\Controllers\Settings\ShippingSettingsController;
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
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/reauthorize', [USPSMerchantConnectionController::class, 'reauthorize'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.reauthorize');
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/usps/manage', [USPSMerchantConnectionController::class, 'manage'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.manage');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/usps/disconnect', [USPSMerchantConnectionController::class, 'disconnect'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.usps-merchant.disconnect');
Route::post('/settings/shipping/carriers/connect/fedex-integrator/origin', [FedExIntegratorConnectionController::class, 'storeOrigin'])
    ->middleware('store.permission:settings.manage')
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
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/capabilities', [FedExValidationCapabilitiesController::class, 'show'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.capabilities');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/final-preflight', [FedExValidationFinalSubmissionController::class, 'finalPreflight'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.final-preflight');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/final-snapshot', [FedExValidationFinalSubmissionController::class, 'createSnapshot'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.final-snapshot');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/final-export/{snapshot}', [FedExValidationFinalSubmissionController::class, 'exportSnapshot'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.final-export');
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
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/eula-review', [FedExValidationRunController::class, 'beginEulaValidationReview'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.eula-review');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/eula-evidence', [FedExValidationArtifactController::class, 'uploadEulaEvidence'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.eula-evidence.upload');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/authorization', [FedExValidationRunController::class, 'runAuthorizationEvidence'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.authorization');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/sweden-passthrough', [FedExValidationRunController::class, 'runSwedenPassthrough'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.sweden-passthrough');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/sweden-screenshots', [FedExValidationArtifactController::class, 'uploadSwedenScreenshots'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.sweden-screenshots.upload');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/branding-screenshots', [FedExValidationArtifactController::class, 'uploadBrandingScreenshot'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.branding-screenshots.upload');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/comprehensive-rate-screenshot', [FedExValidationArtifactController::class, 'uploadComprehensiveRateScreenshot'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.comprehensive-rate-screenshot.upload');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/address', [FedExValidationRunController::class, 'runAddressValidation'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.address');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/service-availability', [FedExValidationRunController::class, 'runServiceAvailability'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.service-availability');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/rate', [FedExValidationRunController::class, 'runRateQuote'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.rate');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/comprehensive-rate', [FedExValidationRunController::class, 'runComprehensiveRateQuote'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.comprehensive-rate');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/mfa/registration-address', [FedExValidationRunController::class, 'runRegistrationAddressValidation'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.mfa.registration-address');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/mfa/invoice', [FedExValidationRunController::class, 'runInvoiceValidation'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.mfa.invoice');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/mfa/pin/{method}/generate', [FedExValidationRunController::class, 'runPinGeneration'])
    ->middleware('store.permission:settings.manage')
    ->where('method', 'email|call')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.mfa.pin.generate');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/mfa/pin/{method}/validate', [FedExValidationRunController::class, 'runPinValidation'])
    ->middleware('store.permission:settings.manage')
    ->where('method', 'email|call')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.mfa.pin.validate');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/ship/{testCaseKey}', [FedExValidationRunController::class, 'runLockedShipLabel'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.ship');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/global/{region}/{caseKey}/run', [FedExValidationRunController::class, 'runGlobalShipCase'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.global-ship');
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
