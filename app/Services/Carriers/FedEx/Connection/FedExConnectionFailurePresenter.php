<?php

namespace App\Services\Carriers\FedEx\Connection;

use App\Models\CarrierAccountRegistrationSession;

final class FedExConnectionFailurePresenter
{
    public function message(
        CarrierAccountRegistrationSession $session,
        ?string $code = null,
        ?string $technicalMessage = null,
    ): string {
        $code = strtolower(trim((string) ($code ?? $session->last_error_code)));
        $technicalMessage = strtolower(trim((string) $technicalMessage));
        $haystack = $code.' '.$technicalMessage;

        if ($this->containsAny($haystack, [
            'child_oauth',
            'csp_credentials',
            'child credential oauth',
        ])) {
            return 'FedEx issued account credentials, but the connection could not be verified. Try the connection again or contact support.';
        }

        if ($this->containsAny($haystack, [
            'account_auth_token_expired',
            'mfa_expired',
            'verification_expired',
            'pin_expired',
            'expired pin',
        ])) {
            return 'FedEx verification expired. Start the account connection again and request a new verification code.';
        }

        if ($this->containsAny($haystack, [
            'invalid_pin',
            'pin_invalid',
            'pin_validation',
            'invalid_invoice',
            'invoice_invalid',
            'invoice_validation',
        ])) {
            return 'FedEx could not verify the PIN or invoice details. Check the information and try again.';
        }

        if ($this->containsAny($haystack, [
            'registration_mfa_required',
            'mfa_required',
            'verification_required',
        ])) {
            return 'FedEx requires an additional verification step to connect this account.';
        }

        if ($this->containsAny($haystack, [
            'invalid.input',
            'account_mismatch',
            'address_mismatch',
            'account details',
            'account records',
            'address',
        ])) {
            return 'FedEx could not match the account name or address. Use the exact details shown on the FedEx account.';
        }

        if ($this->containsAny($haystack, [
            'fedex_authorization_blocked',
            'entitlement',
            'forbidden',
            'configuration',
            'not configured',
            'missing credential',
            'platform_oauth_failed',
            'account_auth_token_missing',
        ])) {
            return 'FedEx account access is not available for this connection. Contact your platform administrator or FedEx support.';
        }

        if ($this->containsAny($haystack, [
            'timeout',
            'transport',
            'service_unavailable',
            'temporarily unavailable',
            'http_500',
            'http_502',
            'http_503',
            'http_504',
        ])) {
            return 'FedEx is temporarily unavailable. Wait a moment and try again.';
        }

        return 'FedEx could not complete the account connection. Check the account details and try again.';
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
