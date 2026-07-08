<?php

namespace App\Services\Carriers\USPS\Support;

use App\Models\CarrierAccount;

/**
 * Structured USPS merchant metadata stored in carrier_accounts.connection_context_json.
 *
 * Never store secrets, passwords, or full EPA/account numbers here — masked values only.
 */
final class USPSMerchantConnectionContext
{
    public const CONTEXT_KEY = 'usps_merchant';

    public function __construct(
        private readonly CarrierAccount $account,
    ) {}

    public static function for(CarrierAccount $account): self
    {
        return new self($account);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $context = $this->account->connection_context_json;

        return is_array($context[self::CONTEXT_KEY] ?? null)
            ? $context[self::CONTEXT_KEY]
            : [];
    }

    public function merchantCridMasked(): ?string
    {
        $masked = $this->stringValue('merchant_crid_masked');

        if ($masked !== null) {
            return $masked;
        }

        $crid = $this->account->uspsMerchantCrid();

        return $crid !== null ? self::maskIdentifier($crid) : null;
    }

    public function merchantMidMasked(): ?string
    {
        $masked = $this->stringValue('merchant_mid_masked');

        if ($masked !== null) {
            return $masked;
        }

        $mid = $this->account->uspsMerchantMid();

        return $mid !== null ? self::maskIdentifier($mid) : null;
    }

    public function merchantEpaMasked(): ?string
    {
        $masked = $this->stringValue('merchant_epa_masked');

        if ($masked !== null) {
            return $masked;
        }

        $epa = $this->account->uspsMerchantEpa();

        return $epa !== null ? self::maskIdentifier($epa) : null;
    }

    public function manifestMidMasked(): ?string
    {
        return $this->stringValue('manifest_mid_masked');
    }

    /**
     * @return list<string>
     */
    public function completedWizardSteps(): array
    {
        $steps = $this->all()['completed_wizard_steps'] ?? [];

        return is_array($steps) ? array_values(array_filter($steps, 'is_string')) : [];
    }

    public function hasCompletedStep(string $step): bool
    {
        return in_array($step, $this->completedWizardSteps(), true);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function merge(array $values): void
    {
        $context = is_array($this->account->connection_context_json)
            ? $this->account->connection_context_json
            : [];

        $existing = is_array($context[self::CONTEXT_KEY] ?? null)
            ? $context[self::CONTEXT_KEY]
            : [];

        $context[self::CONTEXT_KEY] = array_merge($existing, $values);
        $this->account->forceFill(['connection_context_json' => $context])->save();
    }

    public function markWizardStepComplete(string $step): void
    {
        $steps = $this->completedWizardSteps();

        if (! in_array($step, $steps, true)) {
            $steps[] = $step;
        }

        $this->merge(['completed_wizard_steps' => $steps]);
    }

    public function authorizationAcknowledgedAt(): ?string
    {
        return $this->stringValue('authorization_acknowledged_at');
    }

    public function oauthAuthorizationVerifiedAt(): ?string
    {
        return $this->stringValue('oauth_authorization_verified_at');
    }

    public function oauthAuthorizationVerificationMethod(): ?string
    {
        return $this->stringValue('oauth_authorization_verification_method');
    }

    public function oauthSubjectIdMasked(): ?string
    {
        return $this->stringValue('oauth_subject_id_masked');
    }

    public function hasOAuthAuthorizationVerified(): bool
    {
        return filled($this->oauthAuthorizationVerifiedAt());
    }

    public static function maskIdentifier(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }

    private function stringValue(string $key): ?string
    {
        $value = $this->all()[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
