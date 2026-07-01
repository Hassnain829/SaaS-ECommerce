<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\FedExValidationArtifact;
use App\Models\Store;

final class FedExFeedbackMatrixBuilder
{
    /**
     * @param  array<string, mixed>  $preflight
     * @return list<array<string, mixed>>
     */
    public function build(Store $store, CarrierAccount $account, array $preflight): array
    {
        $rows = [
            ['item' => 'Parent authorization', 'check_key' => 'parent_authorization'],
            ['item' => 'Child authorization', 'check_key' => 'child_authorization'],
            ['item' => 'Sweden MFA passthrough', 'check_key' => 'sweden_passthrough_address'],
            ['item' => 'Hosted EULA', 'check_key' => 'hosted_eula_acceptance'],
            ['item' => 'Comprehensive Rates endpoint and UI match', 'check_key' => 'comprehensive_rate_transaction'],
            ['item' => 'US Ship transactions', 'check_key' => 'ship_us02_zplii_event'],
            ['item' => 'US labels and scans', 'check_key' => 'ship_us02_zplii_scan_1'],
            ['item' => 'Canada Ship/labels', 'check_key' => 'ship_ca01_pdf_event'],
            ['item' => 'LAC Ship/labels', 'check_key' => 'global_region_lac_scope'],
            ['item' => 'AMEA Ship/labels', 'check_key' => 'global_region_amea_scope'],
            ['item' => 'Europe Ship/labels', 'check_key' => 'global_region_eu_scope'],
            ['item' => 'Tracking', 'check_key' => 'tracking'],
            ['item' => 'FedEx branding', 'check_key' => 'branding_logo_asset'],
            ['item' => 'Legal notice', 'check_key' => 'branding_legal_notice'],
            ['item' => 'Services list', 'check_key' => 'capability_screenshot_'.FedExValidationArtifact::TYPE_FEDEX_SERVICES_PACKAGING_SCREENSHOT],
            ['item' => 'Packaging list', 'check_key' => 'capability_screenshot_'.FedExValidationArtifact::TYPE_FEDEX_SERVICES_PACKAGING_SCREENSHOT],
            ['item' => 'Special handling', 'check_key' => 'capability_screenshot_'.FedExValidationArtifact::TYPE_FEDEX_SPECIAL_HANDLING_SCREENSHOT],
            ['item' => 'Cover Sheet', 'check_key' => 'document_integrator_validation_cover_sheet'],
            ['item' => 'PIW', 'check_key' => 'document_product_information_worksheet'],
            ['item' => 'Customer screenshots', 'check_key' => 'document_customer_facing_screenshots'],
        ];

        $checks = collect($preflight['checks'] ?? [])->keyBy('key');

        return array_map(function (array $row) use ($checks): array {
            $check = $checks->get($row['check_key']);
            $status = (string) ($check['status'] ?? 'incomplete');

            return [
                'fedex_feedback_item' => $row['item'],
                'resolution_status' => match ($status) {
                    'passed' => 'resolved',
                    'not_applicable' => 'not_applicable_confirmed',
                    'waived_confirmed' => 'resolved_by_written_confirmation',
                    'blocked' => 'blocked',
                    default => 'incomplete',
                },
                'implementation_package' => 'Package 8',
                'evidence_folder' => $this->folderForCheck($row['check_key']),
                'canonical_event_artifact_ids' => [
                    'event_id' => $check['event_id'] ?? null,
                    'artifact_id' => $check['artifact_id'] ?? null,
                ],
                'notes' => (string) ($check['explanation'] ?? ''),
                'waiver_reference' => null,
            ];
        }, $rows);
    }

    private function folderForCheck(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'ship_us') => '05_ship_us02_zplii / 06 / 07',
            str_starts_with($key, 'ship_ca') => '08_global_territories/ca',
            str_starts_with($key, 'global_region') => '08_global_territories',
            str_starts_with($key, 'branding_'), str_starts_with($key, 'capability_') => '10_branding_and_capabilities',
            str_starts_with($key, 'document_') => '00_submission_documents',
            $key === 'tracking' => '09_tracking',
            default => '01_registration_mfa',
        };
    }
}
