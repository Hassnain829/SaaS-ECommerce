<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccountRegistrationSession;
use Illuminate\Support\Arr;

class FedExRegistrationResponseAnalyzer
{
    /** @var list<string> */
    private const CREDENTIAL_KEY_PATHS = [
        'child_Key',
        'child_key',
        'childKey',
        'customerKey',
        'customer_key',
        'credentials.child_Key',
        'credentials.child_key',
        'credentials.childKey',
        'credentials.customerKey',
        'credentials.customer_key',
    ];

    /** @var list<string> */
    private const CREDENTIAL_SECRET_PATHS = [
        'childSecret',
        'child_secret',
        'customerSecret',
        'customer_secret',
        'customerPassword',
        'customer_password',
        'credentials.childSecret',
        'credentials.child_secret',
        'credentials.customerSecret',
        'credentials.customer_secret',
        'credentials.customerPassword',
        'credentials.customer_password',
    ];

    /** @var list<string> */
    private const MFA_CONTAINER_KEYS = [
        'mfaOptions',
        'mfa_options',
        'authenticationOptions',
        'authentication_options',
        'authOptions',
        'pinOptions',
        'invoiceOptions',
    ];

    /** @var list<string> */
    private const MFA_FLAG_KEYS = [
        'mfaRequired',
        'authenticationRequired',
        'verificationRequired',
    ];

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public function output(?array $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $output = Arr::get($data, 'output', $data);

        return is_array($output) ? $output : [];
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array{customer_key: string, customer_password: string}|null
     */
    public function extractChildCredentials(?array $data): ?array
    {
        $sources = $this->credentialSources($data);
        $customerKey = null;
        $customerPassword = null;

        foreach ($sources as $source) {
            if ($customerKey === null) {
                $customerKey = $this->firstFilledPath($source, self::CREDENTIAL_KEY_PATHS);
            }

            if ($customerPassword === null) {
                $customerPassword = $this->firstFilledPath($source, self::CREDENTIAL_SECRET_PATHS);
            }
        }

        if (! filled($customerKey) || ! filled($customerPassword)) {
            return null;
        }

        return [
            'customer_key' => (string) $customerKey,
            'customer_password' => (string) $customerPassword,
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public function mfaDetected(array $output): bool
    {
        foreach (self::MFA_CONTAINER_KEYS as $key) {
            if (array_key_exists($key, $output)) {
                return true;
            }
        }

        foreach (self::MFA_FLAG_KEYS as $flag) {
            if ($this->isTruthyFlag($output[$flag] ?? null)) {
                return true;
            }

            foreach (self::MFA_CONTAINER_KEYS as $containerKey) {
                $container = $output[$containerKey] ?? null;
                if (is_array($container) && $this->isTruthyFlag($container[$flag] ?? null)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return list<array{method: string, label: string, destination_masked: ?string, raw_key: string}>
     */
    public function parseSanitizedMfaOptions(?array $data): array
    {
        $output = $this->output($data);
        $parsed = [];

        foreach (self::MFA_CONTAINER_KEYS as $key) {
            if (! isset($output[$key])) {
                continue;
            }

            $parsed = array_merge($parsed, $this->parseMfaContainer($output[$key], (string) $key));
        }

        $parsed = $this->uniqueMfaOptions($parsed);

        if ($parsed === [] && $this->mfaDetected($output)) {
            return $this->defaultMfaOptions();
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>|null  $existingSummary
     * @return array<string, mixed>
     */
    public function buildDiagnostics(?array $data, ?array $existingSummary = null): array
    {
        $output = $this->output($data);
        $nestedKeys = [];

        foreach ($output as $key => $value) {
            if (is_array($value)) {
                $nestedKeys[$key] = array_values(array_keys($value));
            }
        }

        $mfaOptions = $this->parseSanitizedMfaOptions($data);

        return [
            'http_status' => $existingSummary['http_status'] ?? null,
            'fedex_transaction_id' => $existingSummary['fedex_transaction_id'] ?? Arr::get($data, 'transactionId'),
            'output_keys' => array_values(array_keys($output)),
            'output_nested_keys' => $nestedKeys,
            'credential_key_detected' => $this->extractChildCredentials($data) !== null
                || $this->firstFilledPath($output, self::CREDENTIAL_KEY_PATHS) !== null,
            'credential_secret_detected' => $this->extractChildCredentials($data) !== null
                || $this->firstFilledPath($output, self::CREDENTIAL_SECRET_PATHS) !== null,
            'mfa_detected' => $this->mfaDetected($output),
            'mfa_option_count' => count($mfaOptions),
            'mfa_option_keys' => array_values(array_map(
                static fn (array $option): string => (string) ($option['raw_key'] ?? $option['method']),
                $mfaOptions,
            )),
            'account_auth_token_detected' => $this->extractAccountAuthToken($data) !== null,
        ];
    }

    public function incompleteRegistrationMessage(): string
    {
        return 'FedEx accepted the registration request but did not return child credentials or MFA options. Check redacted response keys in technical details.';
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array{token: string, expires_at: ?\DateTimeInterface}|null
     */
    public function extractAccountAuthToken(?array $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $candidates = [
            Arr::get($data, 'output.mfaOptions.0.accountAuthToken'),
            Arr::get($data, 'output.accountAuthToken'),
            Arr::get($data, 'accountAuthToken'),
        ];

        $mfaOptions = Arr::get($data, 'output.mfaOptions');
        if (is_array($mfaOptions)) {
            foreach ($mfaOptions as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $candidates[] = $option['accountAuthToken'] ?? null;
            }
        }

        foreach ($candidates as $candidate) {
            $parsed = $this->parseAccountAuthTokenValue($candidate);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function extractMfaDestinationMasked(?array $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        $candidates = [
            Arr::get($data, 'output.mfaOptions.0.phoneNumber'),
            Arr::get($data, 'output.mfaOptions.0.phone'),
            Arr::get($data, 'output.mfaOptions.0.maskedPhoneNumber'),
        ];

        $mfaOptions = Arr::get($data, 'output.mfaOptions');
        if (is_array($mfaOptions)) {
            foreach ($mfaOptions as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $candidates[] = $option['phoneNumber'] ?? $option['phone'] ?? $option['maskedPhoneNumber'] ?? null;
            }
        }

        foreach ($candidates as $candidate) {
            if (! filled($candidate) || ! is_scalar($candidate)) {
                continue;
            }

            $masked = $this->maskDestination((string) $candidate);

            if ($masked !== null) {
                return $masked;
            }
        }

        return null;
    }

    private function parseAccountAuthTokenValue(mixed $value): ?array
    {
        if (is_string($value) && filled($value)) {
            return [
                'token' => $value,
                'expires_at' => null,
            ];
        }

        if (! is_array($value)) {
            return null;
        }

        $token = $value['value'] ?? $value['token'] ?? $value['accountAuthToken'] ?? null;

        if (! filled($token) || ! is_scalar($token)) {
            return null;
        }

        $expires = $value['expiresAt'] ?? $value['expires_at'] ?? $value['expiry'] ?? null;

        return [
            'token' => (string) $token,
            'expires_at' => $this->parseAccountAuthTokenExpiry($expires),
        ];
    }

    private function parseAccountAuthTokenExpiry(mixed $expires): ?\DateTimeInterface
    {
        if ($expires instanceof \DateTimeInterface) {
            return $expires;
        }

        if (! is_string($expires) || trim($expires) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($expires);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return list<array<string, mixed>>
     */
    private function credentialSources(?array $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $sources = [$data];
        $output = Arr::get($data, 'output');

        if (is_array($output)) {
            $sources[] = $output;
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $paths
     */
    private function firstFilledPath(array $source, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = Arr::get($source, $path);

            if (filled($value) && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @return list<array{method: string, label: string, destination_masked: ?string, raw_key: string}>
     */
    private function parseMfaContainer(mixed $container, string $containerKey): array
    {
        if (! is_array($container)) {
            return [];
        }

        if ($this->isListArray($container)) {
            $options = [];

            foreach ($container as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $options = array_merge($options, $this->parseMfaListItem($item));
            }

            return $options;
        }

        if (isset($container['options']) && is_array($container['options']) && ! $this->isListArray($container)) {
            $destination = $this->maskDestination(
                (string) ($container['phoneNumber'] ?? $container['phone'] ?? '')
            );

            return $this->parseFedExCredentialRegistrationOptions($container['options'], $destination);
        }

        $options = [];

        foreach ($container as $key => $value) {
            if (in_array((string) $key, ['accountAuthToken', ...self::MFA_FLAG_KEYS], true)) {
                continue;
            }

            if (in_array((string) $key, ['phoneNumber', 'phone', 'mobile', 'email'], true)) {
                continue;
            }

            if ($value === true) {
                $options[] = $this->normalizeMfaOption(['type' => $key], (string) $key);

                continue;
            }

            if (is_array($value)) {
                $options[] = $this->normalizeMfaOption(array_merge($value, ['type' => $value['type'] ?? $key]), (string) $key);
            }
        }

        if ($options === [] && $container !== []) {
            $options[] = [
                'method' => 'OTHER',
                'label' => 'FedEx verification',
                'destination_masked' => null,
                'raw_key' => $containerKey,
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{method: string, label: string, destination_masked: ?string, raw_key: string}>
     */
    private function parseMfaListItem(array $item): array
    {
        $destination = $this->maskDestination(
            (string) ($item['destination_masked'] ?? $item['maskedDestination'] ?? $item['phoneNumber'] ?? $item['phone'] ?? $item['email'] ?? '')
        );

        if (isset($item['options']) && is_array($item['options'])) {
            return $this->parseFedExCredentialRegistrationOptions($item['options'], $destination);
        }

        $type = (string) ($item['type'] ?? $item['method'] ?? $item['mfaType'] ?? $item['optionType'] ?? '');

        if ($type !== '') {
            return [$this->buildMfaOptionFromType($type, $destination)];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{method: string, label: string, destination_masked: ?string, raw_key: string}>
     */
    private function parseFedExCredentialRegistrationOptions(array $options, ?string $destination): array
    {
        $parsed = [];
        $secureCode = $options['secureCode'] ?? $options['secure_code'] ?? null;

        if (is_array($secureCode)) {
            foreach ($secureCode as $code) {
                if (! is_scalar($code) || ! filled($code)) {
                    continue;
                }

                $parsed[] = $this->buildMfaOptionFromType((string) $code, $destination);
            }
        } elseif (is_scalar($secureCode) && filled($secureCode)) {
            $parsed[] = $this->buildMfaOptionFromType((string) $secureCode, $destination);
        }

        if (filled($options['invoice'] ?? null)) {
            $parsed[] = $this->buildMfaOptionFromType('invoice', null);
        }

        return $parsed;
    }

    /**
     * @return array{method: string, label: string, destination_masked: ?string, raw_key: string}
     */
    private function buildMfaOptionFromType(string $type, ?string $destination): array
    {
        $sessionMethod = $this->mapSessionMethod($type);
        $shareDestination = in_array($sessionMethod, [
            CarrierAccountRegistrationSession::MFA_EMAIL,
            CarrierAccountRegistrationSession::MFA_SMS,
            CarrierAccountRegistrationSession::MFA_CALL,
        ], true);

        return [
            'method' => strtoupper($sessionMethod === CarrierAccountRegistrationSession::MFA_CALL ? 'PHONE' : $sessionMethod),
            'label' => $this->defaultLabelForMethod($sessionMethod),
            'destination_masked' => $shareDestination ? $destination : null,
            'raw_key' => $sessionMethod,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{method: string, label: string, destination_masked: ?string, raw_key: string}
     */
    private function normalizeMfaOption(array $item, string $rawKey): array
    {
        $type = (string) ($item['type'] ?? $item['method'] ?? $item['mfaType'] ?? $item['optionType'] ?? $rawKey);
        $sessionMethod = $this->mapSessionMethod($type);
        $label = trim((string) ($item['label'] ?? $item['description'] ?? $item['displayName'] ?? $this->defaultLabelForMethod($sessionMethod)));
        $destination = $this->maskDestination(
            (string) ($item['destination_masked'] ?? $item['maskedDestination'] ?? $item['destination'] ?? $item['email'] ?? $item['phoneNumber'] ?? $item['phone'] ?? $item['mobile'] ?? '')
        );

        return [
            'method' => strtoupper($sessionMethod === CarrierAccountRegistrationSession::MFA_CALL ? 'PHONE' : $sessionMethod),
            'label' => $label !== '' ? $label : $this->defaultLabelForMethod($sessionMethod),
            'destination_masked' => $destination,
            'raw_key' => $sessionMethod,
        ];
    }

    private function mapSessionMethod(string $type): string
    {
        $normalized = strtolower(trim(str_replace([' ', '-'], '_', $type)));

        return match (true) {
            str_contains($normalized, 'invoice') => CarrierAccountRegistrationSession::MFA_INVOICE,
            str_contains($normalized, 'email') => CarrierAccountRegistrationSession::MFA_EMAIL,
            str_contains($normalized, 'sms') || str_contains($normalized, 'text') => CarrierAccountRegistrationSession::MFA_SMS,
            str_contains($normalized, 'call') || str_contains($normalized, 'phone') || str_contains($normalized, 'voice') => CarrierAccountRegistrationSession::MFA_CALL,
            default => CarrierAccountRegistrationSession::MFA_EMAIL,
        };
    }

    private function defaultLabelForMethod(string $method): string
    {
        return match ($method) {
            CarrierAccountRegistrationSession::MFA_EMAIL => 'Email PIN',
            CarrierAccountRegistrationSession::MFA_SMS => 'SMS PIN',
            CarrierAccountRegistrationSession::MFA_CALL => 'Phone call PIN',
            CarrierAccountRegistrationSession::MFA_INVOICE => 'Invoice verification',
            default => 'FedEx verification',
        };
    }

    /**
     * @param  list<array{method: string, label: string, destination_masked: ?string, raw_key: string}>  $options
     * @return list<array{method: string, label: string, destination_masked: ?string, raw_key: string}>
     */
    private function uniqueMfaOptions(array $options): array
    {
        $unique = [];

        foreach ($options as $option) {
            $unique[$option['raw_key']] = $option;
        }

        return array_values($unique);
    }

    /**
     * @return list<array{method: string, label: string, destination_masked: ?string, raw_key: string}>
     */
    private function defaultMfaOptions(): array
    {
        return [
            $this->normalizeMfaOption(['type' => 'email'], 'email'),
            $this->normalizeMfaOption(['type' => 'sms'], 'sms'),
            $this->normalizeMfaOption(['type' => 'call'], 'call'),
            $this->normalizeMfaOption(['type' => 'invoice'], 'invoice'),
        ];
    }

    private function maskDestination(string $destination): ?string
    {
        $destination = trim($destination);

        if ($destination === '') {
            return null;
        }

        if (str_contains($destination, '***')) {
            return $destination;
        }

        if (str_contains($destination, '@')) {
            [$local, $domain] = explode('@', $destination, 2);

            return substr($local, 0, 1).'***@'.$domain;
        }

        $digits = preg_replace('/\D+/', '', $destination) ?? '';

        return $digits !== '' ? '***'.substr($digits, -4) : '***';
    }

    private function isTruthyFlag(mixed $value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtoupper(trim($value)), ['TRUE', 'Y', 'YES', '1'], true);
    }

    /**
     * @param  array<int, mixed>  $value
     */
    private function isListArray(array $value): bool
    {
        return array_is_list($value);
    }
}
