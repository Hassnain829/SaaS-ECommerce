<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Connection\FedExEulaService;

class FedExHostedEulaEvidenceService
{
    public const EXPORT_FOLDER = '13_hosted_eula';

    public function __construct(
        private readonly FedExEulaService $eulaService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function documentCheck(): array
    {
        $valid = $this->eulaService->isValid();

        return [
            'status' => $valid ? 'passed' : ($this->eulaService->isAvailable() ? 'failed' : 'incomplete'),
            'explanation' => $valid
                ? 'Official hosted FedEx EULA PDF is configured and hash-validated.'
                : 'Official FedEx hosted EULA PDF is missing or does not match the configured hash.',
            'metadata' => $this->eulaService->metadata(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountAcceptanceCheck(CarrierAccount $account): array
    {
        if (! $this->eulaService->isValid()) {
            return [
                'status' => 'incomplete',
                'explanation' => 'Official EULA document must be valid before acceptance can pass.',
                'session' => null,
            ];
        }

        $currentHash = $this->eulaService->hash();
        $currentVersion = $this->eulaService->version();

        if (! filled($account->eula_accepted_at)) {
            return [
                'status' => 'incomplete',
                'explanation' => 'Review and accept the official hosted FedEx EULA.',
                'session' => null,
            ];
        }

        if (! filled($account->eula_document_hash)) {
            return [
                'status' => 'outdated',
                'explanation' => 'Previous EULA acceptance predates the official hosted document. Re-accept the current PDF.',
                'session' => null,
            ];
        }

        if (! hash_equals($currentHash, (string) $account->eula_document_hash)) {
            return [
                'status' => 'outdated',
                'explanation' => 'Accepted EULA hash does not match the current official document. Re-accept after reviewing all pages.',
                'session' => null,
            ];
        }

        if ((string) $account->eula_version !== $currentVersion) {
            return [
                'status' => 'outdated',
                'explanation' => 'Accepted EULA version does not match the current official document version.',
                'session' => null,
            ];
        }

        $session = $this->acceptanceSession($account, $currentHash);

        if ($session === null) {
            return [
                'status' => 'incomplete',
                'explanation' => 'EULA acceptance session evidence is missing.',
                'session' => null,
            ];
        }

        if ($session->eula_scrolled_at === null
            || $session->eula_read_acknowledged_at === null
            || (int) $session->eula_rendered_page_count !== $this->eulaService->expectedPages()) {
            return [
                'status' => 'incomplete',
                'explanation' => 'Full agreement scroll completion and read acknowledgement are required.',
                'session' => $session,
            ];
        }

        return [
            'status' => 'passed',
            'explanation' => 'Current official EULA acceptance recorded for this account.',
            'session' => $session,
        ];
    }

    public function acceptanceSession(CarrierAccount $account, ?string $expectedHash = null): ?CarrierAccountRegistrationSession
    {
        $expectedHash ??= $this->eulaService->isAvailable() ? $this->eulaService->hash() : null;

        if ($expectedHash === null || $expectedHash === '') {
            return null;
        }

        $linked = CarrierAccountRegistrationSession::query()
            ->where('store_id', $account->store_id)
            ->where('carrier_account_id', $account->id)
            ->whereNotNull('eula_accepted_at')
            ->where('eula_document_hash', $expectedHash)
            ->latest('eula_accepted_at')
            ->first();

        if ($linked !== null) {
            return $linked;
        }

        if ($account->registration_session_id === null) {
            return null;
        }

        return CarrierAccountRegistrationSession::query()
            ->where('store_id', $account->store_id)
            ->whereKey($account->registration_session_id)
            ->whereNotNull('eula_accepted_at')
            ->where('eula_document_hash', $expectedHash)
            ->first();
    }

    public function eulaEvidenceUploadAllowed(CarrierAccount $account): bool
    {
        return ($this->accountAcceptanceCheck($account)['status'] ?? '') === 'passed';
    }

    public function findEulaArtifact(
        CarrierAccount $account,
        string $artifactType,
        ?string $expectedHash = null,
    ): ?FedExValidationArtifact {
        $expectedHash ??= $this->eulaService->isAvailable() ? $this->eulaService->hash() : null;

        if ($expectedHash === null || $expectedHash === '') {
            return null;
        }

        $artifact = FedExValidationArtifact::query()
            ->where('store_id', $account->store_id)
            ->where('carrier_account_id', $account->id)
            ->where('artifact_type', $artifactType)
            ->where('artifact_role', FedExValidationArtifact::ROLE_EULA_SCREENSHOT)
            ->where('metadata_json->eula_document_hash', $expectedHash)
            ->where('metadata_json->eula_version', $this->eulaService->version())
            ->latest('id')
            ->first();

        return $this->artifactIntegrityValid($artifact) ? $artifact : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function workspaceStatus(CarrierAccount $account): array
    {
        $document = $this->documentCheck();
        $acceptance = $this->accountAcceptanceCheck($account);
        $fullUi = $this->findEulaArtifact($account, FedExValidationArtifact::TYPE_EULA_FULL_UI_EVIDENCE);
        $confirmation = $this->findEulaArtifact($account, FedExValidationArtifact::TYPE_EULA_ACCEPTANCE_CONFIRMATION);

        return [
            'document_valid' => ($document['status'] ?? '') === 'passed',
            'document_version' => $this->eulaService->version(),
            'document_form_number' => $this->eulaService->formNumber(),
            'expected_pages' => $this->eulaService->expectedPages(),
            'full_agreement_viewed' => ($acceptance['session']?->eula_scrolled_at !== null
                && (int) ($acceptance['session']?->eula_rendered_page_count ?? 0) === $this->eulaService->expectedPages())
                ? 'Complete'
                : 'Incomplete',
            'read_acknowledgement' => $acceptance['session']?->eula_read_acknowledged_at !== null ? 'Accepted' : 'Missing',
            'acceptance_status' => match ($acceptance['status'] ?? 'incomplete') {
                'passed' => 'Passed',
                'outdated' => 'Outdated',
                default => 'Missing',
            },
            'evidence_screenshots' => ($fullUi !== null && $confirmation !== null) ? 'Complete' : 'Missing',
            'upload_allowed' => $this->eulaEvidenceUploadAllowed($account),
        ];
    }

    private function artifactIntegrityValid(?FedExValidationArtifact $artifact): bool
    {
        if ($artifact === null || ! filled($artifact->file_path) || ! filled($artifact->sha256)) {
            return false;
        }

        $path = $artifact->absolutePath();
        if ($path === null || ! is_file($path)) {
            return false;
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return false;
        }

        return hash_file('sha256', $path) === (string) $artifact->sha256;
    }
}
