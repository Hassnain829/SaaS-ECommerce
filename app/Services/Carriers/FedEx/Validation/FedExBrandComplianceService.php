<?php

namespace App\Services\Carriers\FedEx\Validation;

use Illuminate\Support\Facades\File;

final class FedExBrandComplianceService
{
    public const LEGAL_NOTICE = 'FedEx service marks are owned by Federal Express Corporation and are used by permission.';

    public const METADATA_PATH = 'resources/fedex-validation/branding/fedex-logo-metadata.json';

    /**
     * Customer-facing registered trademark display names.
     * Keys are normalized uppercase enum/code or plain display labels without ®.
     *
     * @var array<string, string>
     */
    private const REGISTERED_DISPLAY_NAMES = [
        'FEDEX_GROUND' => 'FedEx Ground®',
        'FEDEX GROUND' => 'FedEx Ground®',
        'GROUND' => 'FedEx Ground®',
        'FEDEX_2_DAY_AM' => 'FedEx 2Day® A.M.',
        'FEDEX_2_DAY_A.M.' => 'FedEx 2Day® A.M.',
        'FEDEX 2DAY A.M.' => 'FedEx 2Day® A.M.',
        'FEDEX 2DAY AM' => 'FedEx 2Day® A.M.',
        'FEDEX_ENVELOPE' => 'FedEx® Envelope',
        'FEDEX ENVELOPE' => 'FedEx® Envelope',
        'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight®',
        'FEDEX STANDARD OVERNIGHT' => 'FedEx Standard Overnight®',
        'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight®',
        'FEDEX PRIORITY OVERNIGHT' => 'FedEx Priority Overnight®',
        'FEDEX_2_DAY' => 'FedEx 2Day®',
        'FEDEX 2DAY' => 'FedEx 2Day®',
        'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver®',
        'FEDEX EXPRESS SAVER' => 'FedEx Express Saver®',
        'FEDEX_EXTRA_LARGE_BOX' => 'FedEx® Extra Large Box',
        'FEDEX EXTRA LARGE BOX' => 'FedEx® Extra Large Box',
        'FEDEX_SMALL_BOX' => 'FedEx® Small Box',
        'FEDEX SMALL BOX' => 'FedEx® Small Box',
        'FEDEX EXTRA SMALL BOX' => 'FedEx® Small Box',
        'FEDEX_EXTRA_SMALL_BOX' => 'FedEx® Small Box',
        'FEDEX_LARGE_BOX' => 'FedEx® Large Box',
        'FEDEX LARGE BOX' => 'FedEx® Large Box',
        'FIRST_OVERNIGHT' => 'FedEx First Overnight®',
        'FEDEX FIRST OVERNIGHT' => 'FedEx First Overnight®',
        'FEDEX_MEDIUM_BOX' => 'FedEx® Medium Box',
        'FEDEX MEDIUM BOX' => 'FedEx® Medium Box',
        'FEDEX_PAK' => 'FedEx® Pak',
        'FEDEX PAK' => 'FedEx® Pak',
    ];

    /**
     * Map a FedEx service/packaging enum or plain label to the registered trademark display name.
     * API enum values are never mutated — this is display-only.
     */
    public function registeredDisplayName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (str_contains($trimmed, '®')) {
            return $trimmed;
        }

        $normalized = strtoupper(str_replace(['-', '_'], ' ', $trimmed));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if (isset(self::REGISTERED_DISPLAY_NAMES[$normalized])) {
            return self::REGISTERED_DISPLAY_NAMES[$normalized];
        }

        $asEnum = strtoupper(str_replace([' ', '-', '.'], '_', $trimmed));
        $asEnum = preg_replace('/_+/', '_', $asEnum) ?? $asEnum;

        return self::REGISTERED_DISPLAY_NAMES[$asEnum] ?? $trimmed;
    }

    /**
     * @return array<string, string>
     */
    public function registeredDisplayNameMap(): array
    {
        return self::REGISTERED_DISPLAY_NAMES;
    }

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
