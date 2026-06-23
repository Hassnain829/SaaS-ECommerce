<?php

namespace App\Http\Controllers\Carrier\Validation;

use App\Http\Controllers\Carrier\Validation\Concerns\ResolvesFedExValidationAccount;
use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Support\FedExConfig;
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
