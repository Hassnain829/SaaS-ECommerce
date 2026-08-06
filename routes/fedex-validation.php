<?php

use App\Http\Controllers\Carrier\Connection\FedExIntegratorConnectionController;
use App\Http\Controllers\Carrier\Operations\FedExCarrierTestController;
use App\Http\Controllers\Carrier\Validation\FedExValidationArtifactController;
use App\Http\Controllers\Carrier\Validation\FedExValidationCapabilitiesController;
use App\Http\Controllers\Carrier\Validation\FedExValidationExportController;
use App\Http\Controllers\Carrier\Validation\FedExValidationFinalSubmissionController;
use App\Http\Controllers\Carrier\Validation\FedExValidationRunController;
use App\Http\Controllers\Carrier\Validation\FedExValidationWorkspaceController;
use App\Http\Controllers\Settings\FedExShippingSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FedEx validation and local/testing developer tools
|--------------------------------------------------------------------------
|
| Loaded only when APP_ENV is local or testing. Controllers still enforce
| validation_mode / Model B flags where required.
|
*/

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
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/freight/us08', [FedExValidationRunController::class, 'runFreightUs08'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.freight-us08');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/us09/upload/letterhead', [FedExValidationRunController::class, 'runUs09UploadLetterhead'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.letterhead');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/us09/upload/signature', [FedExValidationRunController::class, 'runUs09UploadSignature'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.signature');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/us09/ship/image', [FedExValidationRunController::class, 'runUs09ShipImage'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.us09.ship.image');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/us09/upload/document', [FedExValidationRunController::class, 'runUs09UploadDocument'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.document');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/us09/ship/document', [FedExValidationRunController::class, 'runUs09ShipDocument'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.us09.ship.document');
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/validation/run/us10/consolidation', [FedExValidationRunController::class, 'runUs10Consolidation'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.validation.run.us10.consolidation');
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
Route::get('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/debug-payload', [FedExShippingSettingsController::class, 'exportFedExDebugPayload'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.debug-payload');
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
Route::post('/settings/shipping/carrier-accounts/{carrierAccount}/fedex/sandbox-platform-fallback', [FedExShippingSettingsController::class, 'enableFedExSandboxPlatformFallback'])
    ->middleware('store.permission:settings.manage')
    ->name('settings.shipping.carrier-accounts.fedex.sandbox-platform-fallback');

