<?php

namespace App\Support\Security;

/**
 * Validates remote image URLs before server-side HTTP fetch to reduce SSRF risk.
 *
 * DNS is resolved first; all returned addresses must be publicly routable.
 * HTTP redirects are not followed by the caller — redirect targets are not re-validated here.
 */
final class ServerSideImageHttpUrlValidator
{
    public static function isSafeRemoteHttpUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if ($host === '' || $host === 'localhost') {
            return false;
        }

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if (str_contains($host, '%')) {
            return false;
        }

        $ips = self::resolveHostToIps($host);
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! self::isPubliclyRoutableIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function resolveHostToIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return [$host];
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return [strtolower($host)];
        }

        $ips = [];

        $ipv4List = @gethostbynamel($host);
        if (is_array($ipv4List)) {
            foreach ($ipv4List as $ip) {
                $ips[] = $ip;
            }
        }

        if (function_exists('dns_get_record')) {
            $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
            foreach ($aaaa as $row) {
                if (! empty($row['ipv6']) && is_string($row['ipv6'])) {
                    $ips[] = strtolower($row['ipv6']);
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private static function isPubliclyRoutableIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $flags = str_contains($ip, ':')
            ? (FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            : (FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }
}
