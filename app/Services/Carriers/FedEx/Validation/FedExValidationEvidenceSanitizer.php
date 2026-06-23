<?php

namespace App\Services\Carriers\FedEx\Validation;

final class FedExValidationEvidenceSanitizer
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'access_token',
        'refresh_token',
        'bearertoken',
        'client_secret',
        'clientsecret',
        'child_secret',
        'childsecret',
        'child_key',
        'customer_key',
        'apikey',
        'api_key',
        'customerpassword',
        'customer_password',
        'accountauthtoken',
        'pin',
        'verificationpin',
        'onetimepin',
        'otp',
        'password',
        'secret',
        'token',
    ];

    /**
     * @param  array<string>|list<string>  $knownSecrets
     * @return list<array{path: string, reason: string}>
     */
    public function scanForSecrets(mixed $value, array $knownSecrets = [], string $path = '$'): array
    {
        $blockers = [];

        foreach ($knownSecrets as $secret) {
            if (is_string($secret) && $secret !== '' && $this->containsNeedle($value, $secret)) {
                $blockers[] = ['path' => $path, 'reason' => 'known_secret_detected'];
            }
        }

        if (is_string($value)) {
            if (preg_match('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/', $value)) {
                $blockers[] = ['path' => $path, 'reason' => 'bearer_token_in_string'];
            }

            if (preg_match('/[A-Za-z]:\\\\[^\s"\']+/i', $value) || preg_match('#/storage/app/[^\s"\']+#', $value)) {
                $blockers[] = ['path' => $path, 'reason' => 'local_path_detected'];
            }

            return $blockers;
        }

        if (! is_array($value)) {
            return $blockers;
        }

        foreach ($value as $key => $item) {
            $childPath = is_int($key) ? $path.'['.$key.']' : $path.'.'.$key;
            $blockers = array_merge($blockers, $this->scanForSecrets($item, $knownSecrets, $childPath));
        }

        return $blockers;
    }

    /**
     * @param  list<string>  $knownSecrets
     * @return list<array{path: string, reason: string}>
     */
    public function scanStagingDirectory(string $directory, array $knownSecrets = []): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $blockers = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));

            if (! $this->shouldScanStagingFile($relativePath)) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            foreach ($knownSecrets as $secret) {
                if (is_string($secret) && $secret !== '' && str_contains($contents, $secret) && ! str_contains($contents, '[REDACTED]')) {
                    $blockers[] = ['path' => $relativePath, 'reason' => 'known_secret_detected'];
                }
            }

            if (preg_match('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/', $contents) && ! str_contains($contents, 'Bearer [REDACTED]')) {
                $blockers[] = ['path' => $relativePath, 'reason' => 'bearer_token_in_string'];
            }

            if (preg_match('/[A-Za-z]:\\\\[^\s"\']+/i', $contents) || preg_match('#/storage/app/[^\s"\']+#', $contents)) {
                $blockers[] = ['path' => $relativePath, 'reason' => 'local_path_detected'];
            }
        }

        return $blockers;
    }

    private function shouldScanStagingFile(string $relativePath): bool
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return in_array($extension, ['json', 'md', 'txt', 'csv', 'xml', 'log'], true);
    }

    public function sanitize(mixed $value, ?string $path = null): mixed
    {
        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            $childPath = $path === null ? (string) $key : $path.'.'.$key;

            if ($this->isSensitiveKey($normalizedKey)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if ($this->isLabelPayloadKey($normalizedKey, $item)) {
                $sanitized[$key] = $this->redactBinaryPayload($item);

                continue;
            }

            $sanitized[$key] = $this->sanitize($item, $childPath);
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public function sanitizeHeaders(array $headers): array
    {
        $safe = [];

        foreach ($headers as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if ($this->isSensitiveKey($normalizedKey)) {
                $safe[$key] = '[REDACTED]';

                continue;
            }

            $safe[$key] = is_string($value) ? $this->sanitizeString($value) : $value;
        }

        return $safe;
    }

    private function sanitizeString(string $value): string
    {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/', 'Bearer [REDACTED]', $value) ?? $value;

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        if (str_ends_with($key, '_last4')) {
            return false;
        }

        $normalizedKey = str_replace('_', '', $key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            $normalizedFragment = str_replace('_', '', $fragment);

            if (str_contains($normalizedKey, $normalizedFragment)) {
                return true;
            }
        }

        return false;
    }

    private function isLabelPayloadKey(string $key, mixed $value): bool
    {
        return in_array($key, ['encodedlabel', 'label', 'labeldata', 'documentcontent'], true)
            && is_string($value)
            && strlen($value) > 64;
    }

    /**
     * @return array<string, mixed>
     */
    private function redactBinaryPayload(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [
                'redacted' => true,
                'reason' => 'binary label exported as separate artifact',
            ];
        }

        $decoded = base64_decode($value, true);

        return [
            'redacted' => true,
            'reason' => 'binary label exported as separate artifact',
            'sha256' => hash('sha256', $decoded !== false ? $decoded : $value),
            'decoded_bytes' => $decoded !== false ? strlen($decoded) : strlen($value),
        ];
    }

    private function containsNeedle(mixed $value, string $needle): bool
    {
        if (is_string($value)) {
            return str_contains($value, $needle);
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsNeedle($item, $needle)) {
                return true;
            }
        }

        return false;
    }
}
