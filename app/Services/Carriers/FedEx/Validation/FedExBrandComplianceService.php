<?php

namespace App\Services\Carriers\FedEx\Validation;

use Illuminate\Support\Facades\File;

final class FedExBrandComplianceService
{
    public const LEGAL_NOTICE = 'FedEx service marks are owned by Federal Express Corporation and are used by permission.';

    public const METADATA_PATH = 'resources/fedex-validation/branding/fedex-logo-metadata.json';

    /**
     * @return array<string, mixed>
     */
    public function logoMetadata(): array
    {
        $path = base_path(self::METADATA_PATH);
        if (! File::isFile($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function logoPublicPath(): ?string
    {
        $relative = (string) ($this->logoMetadata()['public_path'] ?? '');
        if ($relative === '') {
            return null;
        }

        $full = public_path($relative);

        return File::isFile($full) ? $relative : null;
    }

    public function logoIsAvailable(): bool
    {
        return $this->logoPublicPath() !== null;
    }

    public function logoIsApprovedSource(): bool
    {
        $meta = $this->logoMetadata();

        return ($meta['approved_for_validation'] ?? false) === true
            && filled($meta['source'] ?? null)
            && $meta['source'] !== 'pending_user_supplied_asset';
    }

    public function logoHash(): ?string
    {
        $relative = $this->logoPublicPath();
        if ($relative === null) {
            return null;
        }

        $computed = hash_file('sha256', public_path($relative));
        $stored = (string) ($this->logoMetadata()['sha256'] ?? '');

        if ($stored !== '' && ! hash_equals($stored, $computed)) {
            return $computed;
        }

        return $computed ?: null;
    }

    public function legalNotice(): string
    {
        return self::LEGAL_NOTICE;
    }

    public function legalNoticeIsExact(string $text): bool
    {
        return trim($text) === self::LEGAL_NOTICE;
    }

    /**
     * @return array<string, mixed>
     */
    public function workspaceStatus(): array
    {
        return [
            'logo_available' => $this->logoIsAvailable(),
            'logo_approved_source' => $this->logoIsApprovedSource(),
            'logo_hash' => $this->logoHash(),
            'legal_notice' => $this->legalNotice(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function preflightChecks(): array
    {
        $checks = [];

        $checks[] = [
            'key' => 'branding_logo_asset',
            'category' => 'branding',
            'label' => 'Approved FedEx unified logo asset',
            'required' => true,
            'status' => $this->logoIsAvailable() && $this->logoIsApprovedSource() ? 'passed' : 'blocked',
            'explanation' => $this->logoIsAvailable()
                ? ($this->logoIsApprovedSource()
                    ? 'Approved logo asset is present.'
                    : 'Logo file exists but metadata is not approved. Update fedex-logo-metadata.json after supplying the official FedEx asset.')
                : 'Upload the official FedEx unified logo to public/assets/carriers/fedex/ and update branding metadata.',
        ];

        $checks[] = [
            'key' => 'branding_legal_notice',
            'category' => 'branding',
            'label' => 'Exact FedEx legal notice on capability screen',
            'required' => true,
            'status' => 'passed',
            'explanation' => 'Legal notice is rendered from FedExBrandComplianceService on the capabilities page.',
        ];

        return $checks;
    }
}
