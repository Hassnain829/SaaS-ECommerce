<?php

namespace App\Services\Carriers\USPS\Support;

use App\Models\CarrierAccount;

/**
 * Builds Payments 3.0 role payloads for merchant-owned postage verification.
 *
 * Merchant EPA remains PAYER + RATE_HOLDER and merchant MID remains LABEL_OWNER.
 * PLATFORM and LABEL_PROVIDER role names and inclusion are configurable until USPS ICD is final.
 */
final class USPSPaymentRolePayloadBuilder
{
    public function __construct(
        private readonly USPSConfig $config,
    ) {}

    /**
     * @return array{roles: list<array<string, mixed>>}
     */
    public function build(CarrierAccount $account): array
    {
        abort_unless($account->hasUspsMerchantIdentifiers(), 422);

        $roles = [
            $this->merchantEpsRole('PAYER', $account, includeManifestMid: true),
            $this->merchantEpsRole('RATE_HOLDER', $account, includeManifestMid: false),
            $this->labelOwnerRole($account),
        ];

        $platformRole = $this->platformRole();
        if ($platformRole !== null) {
            $roles[] = $platformRole;
        }

        if ($this->config->paymentIncludeLabelProviderRole()) {
            $labelProviderRole = $this->labelProviderRole();
            if ($labelProviderRole !== null) {
                $roles[] = $labelProviderRole;
            }
        }

        return ['roles' => $roles];
    }

    /**
     * @return array<string, mixed>
     */
    private function merchantEpsRole(string $roleName, CarrierAccount $account, bool $includeManifestMid): array
    {
        $role = [
            'roleName' => $roleName,
            'CRID' => (string) $account->uspsMerchantCrid(),
            'MID' => (string) $account->uspsMerchantMid(),
            'accountType' => $this->config->paymentAccountType(),
            'accountNumber' => (string) $account->uspsMerchantEpa(),
        ];

        $manifestMid = $account->uspsMerchantManifestMid() ?? $account->uspsMerchantMid();
        if ($includeManifestMid && filled($manifestMid)) {
            $role['manifestMID'] = (string) $manifestMid;
        }

        return $role;
    }

    /**
     * @return array<string, mixed>
     */
    private function labelOwnerRole(CarrierAccount $account): array
    {
        $role = [
            'roleName' => 'LABEL_OWNER',
            'CRID' => (string) $account->uspsMerchantCrid(),
            'MID' => (string) $account->uspsMerchantMid(),
        ];

        $manifestMid = $account->uspsMerchantManifestMid();
        if (filled($manifestMid)) {
            $role['manifestMID'] = (string) $manifestMid;
        }

        return $role;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function platformRole(): ?array
    {
        $crid = $this->config->platformCrid();
        $epa = $this->config->platformEpa();
        $mid = $this->config->platformMasterMid();

        if (! filled($crid) || ! filled($epa) || ! filled($mid)) {
            return null;
        }

        return [
            'roleName' => $this->config->paymentPlatformRoleName(),
            'CRID' => (string) $crid,
            'MID' => (string) $mid,
            'accountType' => $this->config->paymentAccountType(),
            'accountNumber' => (string) $epa,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function labelProviderRole(): ?array
    {
        $crid = $this->config->platformCrid();
        $epa = $this->config->platformEpa();
        $mid = $this->config->platformMasterMid();

        if (! filled($crid) || ! filled($epa) || ! filled($mid)) {
            return null;
        }

        return [
            'roleName' => $this->config->paymentLabelProviderRoleName(),
            'CRID' => (string) $crid,
            'MID' => (string) $mid,
            'accountType' => $this->config->paymentAccountType(),
            'accountNumber' => (string) $epa,
        ];
    }
}
