<?php

namespace App\Services\Carriers\USPS\Support;

/**
 * Parses USPS OIDC userinfo payloads for merchant authorization verification.
 */
final class USPSUserInfoParser
{
    /**
     * @param  array<string, mixed>  $userinfo
     * @return array{crids: list<string>, mids: list<string>, epas: list<string>}
     */
    public function extractIdentifiers(array $userinfo): array
    {
        $crids = [];
        $mids = [];
        $epas = [];

        $mailOwners = $userinfo['mail_owners'] ?? [];

        if (is_array($mailOwners)) {
            foreach ($mailOwners as $owner) {
                if (! is_array($owner)) {
                    continue;
                }

                $crid = $this->normalizeIdentifier($owner['crid'] ?? null);

                if ($crid !== null) {
                    $crids[] = $crid;
                }

                foreach ($owner['mids'] ?? [] as $mid) {
                    $normalizedMid = $this->normalizeIdentifier($mid);

                    if ($normalizedMid !== null) {
                        $mids[] = $normalizedMid;
                    }
                }
            }
        }

        $paymentAccounts = data_get($userinfo, 'payment_accounts.accounts', []);

        if (is_array($paymentAccounts)) {
            foreach ($paymentAccounts as $account) {
                $epa = $this->extractPaymentAccountNumber($account);

                if ($epa !== null) {
                    $epas[] = $epa;
                }
            }
        }

        return [
            'crids' => array_values(array_unique($crids)),
            'mids' => array_values(array_unique($mids)),
            'epas' => array_values(array_unique($epas)),
        ];
    }

    /**
     * @param  array{crids: list<string>, mids: list<string>, epas: list<string>}  $extracted
     * @return array{matched: bool, inconclusive: bool, code: string, message: string}
     */
    public function validateAgainstMerchantAccount(
        array $extracted,
        string $expectedCrid,
        string $expectedMid,
        string $expectedEpa,
    ): array {
        if ($extracted['crids'] === [] && $extracted['mids'] === []) {
            return [
                'matched' => false,
                'inconclusive' => true,
                'code' => 'verification_inconclusive',
                'message' => 'USPS did not return mail owner identifiers in the authorization profile. Reauthorize with USPS or contact support if this continues.',
            ];
        }

        if ($extracted['crids'] !== [] && ! in_array($expectedCrid, $extracted['crids'], true)) {
            return [
                'matched' => false,
                'inconclusive' => false,
                'code' => 'identifier_mismatch',
                'message' => 'The USPS account returned by authorization does not match the CRID you entered in BmyBrand.',
            ];
        }

        if ($extracted['mids'] !== [] && ! in_array($expectedMid, $extracted['mids'], true)) {
            return [
                'matched' => false,
                'inconclusive' => false,
                'code' => 'identifier_mismatch',
                'message' => 'The USPS account returned by authorization does not match the Mailer ID you entered in BmyBrand.',
            ];
        }

        if ($extracted['epas'] !== [] && ! in_array($expectedEpa, $extracted['epas'], true)) {
            return [
                'matched' => false,
                'inconclusive' => false,
                'code' => 'identifier_mismatch',
                'message' => 'The USPS payment account returned by authorization does not match the EPA you entered in BmyBrand.',
            ];
        }

        return [
            'matched' => true,
            'inconclusive' => false,
            'code' => 'identifiers_matched',
            'message' => 'USPS account identifiers match.',
        ];
    }

    private function extractPaymentAccountNumber(mixed $account): ?string
    {
        if (is_string($account) || is_numeric($account)) {
            return $this->normalizeIdentifier($account);
        }

        if (! is_array($account)) {
            return null;
        }

        foreach (['account_number', 'epa', 'enterprise_payment_account', 'payment_account_number'] as $key) {
            $value = $this->normalizeIdentifier($account[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeIdentifier(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
