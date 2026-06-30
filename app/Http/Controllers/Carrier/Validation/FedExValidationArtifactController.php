<?php

namespace App\Http\Controllers\Carrier\Validation;

use App\Http\Controllers\Carrier\Validation\Concerns\ResolvesFedExValidationAccount;
use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExComprehensiveRateEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExHostedEulaEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceQueryService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FedExValidationArtifactController extends Controller
{
    use ResolvesFedExValidationAccount;

    public function uploadDocument(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:'.FedExValidationArtifact::DOC_COVER_SHEET.','.FedExValidationArtifact::DOC_PIW.','.FedExValidationArtifact::DOC_CUSTOMER_SCREENSHOTS],
            'document' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $this->storeUploadedFile(
            account: $account,
            file: $request->file('document'),
            artifactType: $validated['document_type'],
            role: FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT,
            actorId: $request->user()?->id,
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_document_upload', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'document_type' => $validated['document_type'],
        ]);

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('success', 'Validation document uploaded.');
    }

    public function uploadPrintedScan(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationEvidenceQueryService $evidenceQuery,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;

        $validated = $request->validate([
            'test_case_key' => ['required', 'string', 'in:IntegratorUS02,IntegratorUS04,IntegratorUS05'],
            'package_sequence' => ['required', 'integer', 'min:1', 'max:5'],
            'scan_dpi' => ['required', 'integer', 'min:600', 'max:2400'],
            'scan' => ['required', 'file', 'mimes:pdf,png', 'max:20480'],
        ]);

        $meta = FedExValidationScenarioCatalog::lockedShipScenarios()[$validated['test_case_key']];
        $event = $evidenceQuery->canonicalShipLabelEvent(
            $store,
            $account,
            (string) $meta['scenario_key'],
            testCaseKey: $validated['test_case_key'],
            labelFormat: (string) $meta['label_format'],
        );

        abort_unless(
            $event !== null && $event->isSuccessfulHttp() && $event->hasCompleteEvidence(),
            422,
            'Upload a printed scan only after the successful locked '.$validated['test_case_key'].' ship run completes.',
        );

        $this->storeUploadedFile(
            account: $account,
            file: $request->file('scan'),
            artifactType: 'printed_scan_'.$validated['test_case_key'].'_'.$validated['package_sequence'],
            role: FedExValidationArtifact::ROLE_PRINTED_SCAN,
            actorId: $request->user()?->id,
            extra: [
                'carrier_api_event_id' => $event->id,
                'scenario_key' => $meta['scenario_key'],
                'test_case_key' => $validated['test_case_key'],
                'label_format' => $meta['label_format'],
                'package_sequence' => (int) $validated['package_sequence'],
                'scan_dpi' => (int) $validated['scan_dpi'],
                'metadata_json' => ['scan_dpi_source' => 'user_confirmed'],
            ],
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_scan_upload', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'test_case_key' => $validated['test_case_key'],
            'package_sequence' => $validated['package_sequence'],
            'carrier_api_event_id' => $event->id,
        ]);

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('success', 'Printed scan uploaded and linked to the canonical ship event.');
    }

    public function uploadTrackingScreenshot(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationEvidenceQueryService $evidenceQuery,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;

        $validated = $request->validate([
            'screenshot' => ['required', 'file', 'mimes:pdf,png', 'max:20480'],
        ]);

        $trackingEvent = $evidenceQuery->canonicalSuccessfulEvent($store, $account, 'basic_integrated_visibility');

        abort_unless(
            $trackingEvent !== null && $trackingEvent->isSuccessfulHttp(),
            422,
            'Upload a tracking screenshot only after a successful tracking run.',
        );

        $this->storeUploadedFile(
            account: $account,
            file: $request->file('screenshot'),
            artifactType: 'tracking_screenshot',
            role: FedExValidationArtifact::ROLE_TRACKING_SCREENSHOT,
            actorId: $request->user()?->id,
            extra: [
                'carrier_api_event_id' => $trackingEvent->id,
                'scenario_key' => 'basic_integrated_visibility',
            ],
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_tracking_screenshot_upload', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'carrier_api_event_id' => $trackingEvent->id,
        ]);

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('success', 'Tracking screenshot uploaded.');
    }

    public function uploadEulaEvidence(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExHostedEulaEvidenceService $hostedEulaEvidence,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;

        abort_unless(
            $hostedEulaEvidence->eulaEvidenceUploadAllowed($account),
            422,
            'Upload EULA evidence only after current-document acceptance is recorded.',
        );

        $acceptanceSession = $hostedEulaEvidence->acceptanceSession($account);
        abort_unless($acceptanceSession !== null, 422);

        $validated = $request->validate([
            'full_ui_evidence' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'acceptance_confirmation' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:20480'],
        ]);

        $metadata = [
            'eula_document_hash' => (string) $account->eula_document_hash,
            'eula_version' => (string) $account->eula_version,
            'registration_session_id' => $acceptanceSession->id,
        ];

        $this->storeUploadedFile(
            account: $account,
            file: $request->file('full_ui_evidence'),
            artifactType: FedExValidationArtifact::TYPE_EULA_FULL_UI_EVIDENCE,
            role: FedExValidationArtifact::ROLE_EULA_SCREENSHOT,
            actorId: $request->user()?->id,
            extra: [
                'registration_session_id' => $acceptanceSession->id,
                'metadata_json' => $metadata,
            ],
        );

        $this->storeUploadedFile(
            account: $account,
            file: $request->file('acceptance_confirmation'),
            artifactType: FedExValidationArtifact::TYPE_EULA_ACCEPTANCE_CONFIRMATION,
            role: FedExValidationArtifact::ROLE_EULA_SCREENSHOT,
            actorId: $request->user()?->id,
            extra: [
                'registration_session_id' => $acceptanceSession->id,
                'metadata_json' => $metadata,
            ],
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_eula_evidence_upload', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'registration_session_id' => $acceptanceSession->id,
            'eula_document_hash' => $account->eula_document_hash,
        ]);

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('success', 'Hosted EULA evidence uploaded.');
    }

    public function uploadSwedenScreenshots(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationEvidenceQueryService $evidenceQuery,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;

        $validated = $request->validate([
            'address_screenshot' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:20480'],
            'child_authorization_screenshot' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:20480'],
        ]);

        $pairedRun = $evidenceQuery->canonicalSwedenPassthroughRun($store, $account);

        abort_unless(
            $pairedRun !== null,
            422,
            'Upload Sweden passthrough screenshots only after a successful paired Sweden MFA passthrough run.',
        );

        $runId = $pairedRun['validation_run_id'];
        $addressEvent = $pairedRun['address_event'];
        $childEvent = $pairedRun['child_authorization_event'];

        $addressMeta = [
            'validation_run_id' => $runId,
            'address_event_id' => $addressEvent->id,
            'child_authorization_event_id' => $childEvent->id,
        ];

        $this->storeUploadedFile(
            account: $account,
            file: $request->file('address_screenshot'),
            artifactType: FedExValidationArtifact::TYPE_SWEDEN_PASSTHROUGH_ADDRESS_SCREENSHOT,
            role: FedExValidationArtifact::ROLE_SWEDEN_PASSTHROUGH_SCREENSHOT,
            actorId: $request->user()?->id,
            extra: [
                'carrier_api_event_id' => $addressEvent->id,
                'scenario_key' => CarrierApiEvent::SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS,
                'metadata_json' => $addressMeta,
            ],
        );

        $this->storeUploadedFile(
            account: $account,
            file: $request->file('child_authorization_screenshot'),
            artifactType: FedExValidationArtifact::TYPE_SWEDEN_PASSTHROUGH_CHILD_AUTH_SCREENSHOT,
            role: FedExValidationArtifact::ROLE_SWEDEN_PASSTHROUGH_SCREENSHOT,
            actorId: $request->user()?->id,
            extra: [
                'carrier_api_event_id' => $childEvent->id,
                'scenario_key' => CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD,
                'metadata_json' => $addressMeta,
            ],
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_sweden_screenshot_upload', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'validation_run_id' => $runId,
            'address_event_id' => $addressEvent->id,
            'child_authorization_event_id' => $childEvent->id,
        ]);

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('success', 'Sweden passthrough screenshots uploaded.');
    }

    public function uploadComprehensiveRateScreenshot(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExComprehensiveRateEvidenceService $comprehensiveRateEvidence,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;

        $validated = $request->validate([
            'screenshot' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:20480'],
        ]);

        $canonical = $comprehensiveRateEvidence->canonicalEvent($store, $account);
        abort_unless($canonical !== null, 422, 'Upload a comprehensive rate screenshot only after a successful canonical rate event exists.');

        $metadata = [
            'scenario_key' => $canonical->scenario_key,
            'service_type' => data_get($canonical->response_summary, 'service_type'),
            'rate_type' => data_get($canonical->response_summary, 'rate_type'),
            'currency' => data_get($canonical->response_summary, 'currency'),
            'amount' => data_get($canonical->response_summary, 'amount'),
        ];

        FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('carrier_api_event_id', $canonical->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_COMPREHENSIVE_RATE_SCREENSHOT)
            ->delete();

        $this->storeUploadedFile(
            account: $account,
            file: $request->file('screenshot'),
            artifactType: FedExValidationArtifact::TYPE_COMPREHENSIVE_RATE_RESULT_UI,
            role: FedExValidationArtifact::ROLE_COMPREHENSIVE_RATE_SCREENSHOT,
            actorId: $request->user()?->id,
            extra: [
                'carrier_api_event_id' => $canonical->id,
                'scenario_key' => $canonical->scenario_key,
                'metadata_json' => $metadata,
            ],
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_comprehensive_rate_screenshot_upload', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'rate_event_id' => $canonical->id,
            'currency' => $metadata['currency'],
            'amount' => $metadata['amount'],
        ]);

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('success', 'Comprehensive rate screenshot uploaded.');
    }

    public function download(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExValidationArtifact $artifact,
        FedExConfig $config,
    ): BinaryFileResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        abort_unless((int) $artifact->store_id === (int) $account->store_id, 404);
        abort_unless((int) $artifact->carrier_account_id === (int) $account->id, 404);

        $path = $artifact->absolutePath();
        abort_unless($path !== null && str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', storage_path('app/fedex-validation'))), 404);
        abort_unless(is_file($path), 404);

        return response()->download($path, $artifact->original_filename ?: basename($path));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function storeUploadedFile(
        CarrierAccount $account,
        \Illuminate\Http\UploadedFile $file,
        string $artifactType,
        string $role,
        ?int $actorId,
        array $extra = [],
    ): FedExValidationArtifact {
        $binary = file_get_contents($file->getRealPath()) ?: '';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $filename = Str::slug($artifactType).'-'.now()->format('YmdHis').'.'.$extension;
        $relativePath = "fedex-validation/{$account->store_id}/uploads/{$filename}";
        $absolutePath = storage_path('app/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, $binary);

        return FedExValidationArtifact::query()->create(array_merge([
            'store_id' => $account->store_id,
            'carrier_account_id' => $account->id,
            'registration_session_id' => $account->registration_session_id,
            'environment' => $account->environment,
            'artifact_type' => $artifactType,
            'artifact_role' => $role,
            'label' => str($artifactType)->replace('_', ' ')->title(),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'file_path' => $relativePath,
            'created_by' => $actorId,
        ], $extra));
    }
}
