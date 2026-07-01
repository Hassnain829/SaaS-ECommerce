<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use App\Services\Carriers\FedEx\Capabilities\FedExCapabilityRegistry;

final class FedExCapabilityEvidenceService
{
    public function __construct(
        private readonly FedExCapabilityRegistry $registry,
        private readonly FedExBrandComplianceService $brandCompliance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function customerFacingCapabilities(): array
    {
        return $this->registry->customerFacingCapabilities();
    }

    /**
     * @return array<string, mixed>
     */
    public function validationCapabilities(): array
    {
        return $this->registry->exportSummary();
    }

    /**
     * @return list<string>
     */
    public function unsupportedClaims(): array
    {
        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function preflightChecks(Store $store, CarrierAccount $account): array
    {
        $checks = [];
        $brandingArtifacts = $this->latestBrandingArtifacts($store, $account);
        $logoHash = $this->brandCompliance->logoHash();

        foreach ([
            FedExValidationArtifact::TYPE_FEDEX_BRANDING_UI_SCREENSHOT => 'Branding and legal notice screenshot',
            FedExValidationArtifact::TYPE_FEDEX_SERVICES_PACKAGING_SCREENSHOT => 'Services and packaging screenshot',
            FedExValidationArtifact::TYPE_FEDEX_SPECIAL_HANDLING_SCREENSHOT => 'Special handling screenshot',
        ] as $type => $label) {
            $artifact = $brandingArtifacts[$type] ?? null;
            $stale = $artifact !== null
                && filled($logoHash)
                && filled(data_get($artifact->metadata_json, 'logo_sha256'))
                && ! hash_equals((string) data_get($artifact->metadata_json, 'logo_sha256'), (string) $logoHash);

            $checks[] = [
                'key' => 'capability_screenshot_'.$type,
                'category' => 'branding',
                'label' => $label,
                'required' => true,
                'status' => $artifact === null ? 'incomplete' : ($stale ? 'outdated' : 'passed'),
                'explanation' => $artifact === null
                    ? 'Capture the capabilities page (?evidence_mode=1) and upload the screenshot.'
                    : ($stale ? 'Screenshot is stale after logo change. Recapture and upload.' : 'Screenshot uploaded and current.'),
                'artifact_id' => $artifact?->id,
            ];
        }

        if ($this->unsupportedClaims() !== []) {
            $checks[] = [
                'key' => 'capability_unsupported_claims',
                'category' => 'branding',
                'label' => 'Capability registry claim integrity',
                'required' => true,
                'status' => 'failed',
                'explanation' => 'Registry contains unsupported production claims.',
            ];
        }

        return $checks;
    }

    /**
     * @return array<string, mixed>
     */
    public function exportSummary(): array
    {
        return [
            'registry_version' => FedExCapabilityRegistry::VERSION,
            'legal_notice_version' => hash('sha256', $this->brandCompliance->legalNotice()),
            'capabilities' => $this->registry->exportSummary(),
        ];
    }

    /**
     * @return array<string, FedExValidationArtifact>
     */
    private function latestBrandingArtifacts(Store $store, CarrierAccount $account): array
    {
        $types = [
            FedExValidationArtifact::TYPE_FEDEX_BRANDING_UI_SCREENSHOT,
            FedExValidationArtifact::TYPE_FEDEX_SERVICES_PACKAGING_SCREENSHOT,
            FedExValidationArtifact::TYPE_FEDEX_SPECIAL_HANDLING_SCREENSHOT,
        ];

        $artifacts = [];
        foreach ($types as $type) {
            $artifact = FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('artifact_type', $type)
                ->latest('id')
                ->first();
            if ($artifact !== null) {
                $artifacts[$type] = $artifact;
            }
        }

        return $artifacts;
    }
}
