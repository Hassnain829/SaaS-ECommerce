<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\FedExValidationSubmissionSnapshot;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Capabilities\FedExCapabilityRegistry;
use App\Services\Carriers\FedEx\Validation\Preflight\GlobalShipCheckProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class FedExFinalSubmissionService
{
    public function __construct(
        private readonly FedExValidationPreflightService $preflight,
        private readonly FedExBrandComplianceService $brandCompliance,
        private readonly FedExCapabilityEvidenceService $capabilityEvidence,
        private readonly GlobalShipCheckProvider $globalShipChecks,
        private readonly FedExValidationEvidenceExporter $exporter,
        private readonly FedExFeedbackMatrixBuilder $feedbackMatrix,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function runFinalPreflight(Store $store, CarrierAccount $account): array
    {
        $assessment = $this->preflight->assess($store, $account, null, includePackageEight: true);

        return array_merge($assessment, [
            'final_submission_preflight' => true,
            'no_api_mutations' => true,
        ]);
    }

    public function createSnapshot(
        Store $store,
        CarrierAccount $account,
        ?User $actor = null,
        ?string $caseReference = null,
    ): FedExValidationSubmissionSnapshot {
        $assessment = $this->runFinalPreflight($store, $account);
        abort_unless($assessment['ready'] ?? false, 422, 'Final submission snapshot is blocked until all mandatory checks pass.');

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'checks' => $assessment['checks'] ?? [],
            'blockers' => $assessment['blockers'] ?? [],
            'selected_scopes' => $assessment['selected_scopes'] ?? [],
            'capability_registry_version' => FedExCapabilityRegistry::VERSION,
            'logo_sha256' => $this->brandCompliance->logoHash(),
        ];

        return FedExValidationSubmissionSnapshot::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'case_reference' => $caseReference,
            'status' => FedExValidationSubmissionSnapshot::STATUS_READY,
            'preflight_hash' => (string) ($assessment['preflight_hash'] ?? hash('sha256', json_encode($manifest['checks']))),
            'snapshot_manifest_json' => $manifest,
            'evidence_ids_json' => $assessment['canonical_event_ids'] ?? [],
            'artifact_ids_json' => collect($assessment['checks'] ?? [])
                ->pluck('artifact_id')
                ->filter()
                ->values()
                ->all(),
            'capability_registry_version' => FedExCapabilityRegistry::VERSION,
            'logo_sha256' => $this->brandCompliance->logoHash(),
            'created_by' => $actor?->id,
            'finalized_at' => now(),
        ]);
    }

    public function exportSnapshot(FedExValidationSubmissionSnapshot $snapshot): string
    {
        abort_unless($snapshot->isReady(), 422, 'Snapshot is not ready for export.');

        $account = $snapshot->carrierAccount;
        $store = $snapshot->store;
        abort_if($account === null || $store === null, 422, 'Snapshot scope is invalid.');

        if ($this->snapshotIsStale($snapshot)) {
            $snapshot->forceFill([
                'status' => FedExValidationSubmissionSnapshot::STATUS_INVALIDATED,
                'invalidated_at' => now(),
                'invalidation_reason' => 'Evidence or branding changed after snapshot was created.',
            ])->save();

            throw new HttpException(422, 'Snapshot invalidated — run final preflight and create a new snapshot.');
        }

        $caseRef = Str::slug($snapshot->case_reference ?: 'Americas', '_');
        $timestamp = now()->format('Ymd_His');
        $zipPath = $this->exporter->exportFinalSubmission(
            store: $store,
            account: $account,
            snapshot: $snapshot,
            caseReference: $caseRef,
            timestamp: $timestamp,
        );

        $snapshot->forceFill([
            'status' => FedExValidationSubmissionSnapshot::STATUS_EXPORTED,
            'export_zip_path' => $zipPath,
        ])->save();

        return $zipPath;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groupedReadiness(Store $store, CarrierAccount $account): array
    {
        $assessment = $this->runFinalPreflight($store, $account);
        $groups = [
            'authorization' => 'Authorization',
            'registration' => 'Registration and MFA',
            'sweden' => 'Sweden passthrough',
            'eula' => 'Hosted EULA',
            'core_api' => 'Address and service APIs',
            'comprehensive_rates' => 'Comprehensive Rates',
            'us_ship' => 'US Ship and labels',
            'global_territories' => 'Global territories',
            'tracking' => 'Tracking',
            'branding' => 'Branding and capability disclosure',
            'documents' => 'Required documents',
        ];

        $result = [];
        foreach ($groups as $key => $label) {
            $items = collect($assessment['checks'] ?? [])->filter(function (array $check) use ($key): bool {
                $category = (string) ($check['category'] ?? '');

                return match ($key) {
                    'authorization' => $category === 'authorization',
                    'registration' => $category === 'registration',
                    'sweden' => str_starts_with((string) ($check['key'] ?? ''), 'sweden_'),
                    'eula' => str_starts_with((string) ($check['key'] ?? ''), 'hosted_eula'),
                    'core_api' => in_array((string) ($check['key'] ?? ''), ['address_validation', 'service_availability'], true),
                    'comprehensive_rates' => str_starts_with((string) ($check['key'] ?? ''), 'comprehensive_rate'),
                    'us_ship' => str_starts_with((string) ($check['key'] ?? ''), 'ship_'),
                    'global_territories' => $category === 'global_territories',
                    'tracking' => str_starts_with((string) ($check['key'] ?? ''), 'tracking'),
                    'branding' => $category === 'branding',
                    'documents' => $category === 'documents',
                    default => false,
                };
            });

            $required = $items->where('required', true);
            $passed = $required->where('status', 'passed')->count();
            $total = $required->count();

            $result[] = [
                'key' => $key,
                'label' => $label,
                'passed' => $passed,
                'total' => $total,
                'status' => $total === 0 ? 'not_applicable' : ($passed === $total ? 'passed' : 'incomplete'),
            ];
        }

        return $result;
    }

    private function snapshotIsStale(FedExValidationSubmissionSnapshot $snapshot): bool
    {
        $currentLogo = $this->brandCompliance->logoHash();
        if (filled($snapshot->logo_sha256) && filled($currentLogo) && ! hash_equals((string) $snapshot->logo_sha256, (string) $currentLogo)) {
            return true;
        }

        if ($snapshot->capability_registry_version !== FedExCapabilityRegistry::VERSION) {
            return true;
        }

        $current = $this->runFinalPreflight($snapshot->store, $snapshot->carrierAccount);

        return ($current['preflight_hash'] ?? null) !== null
            && hash('sha256', json_encode($current['checks'] ?? [])) !== (string) $snapshot->preflight_hash;
    }
}
