<?php

namespace App\Services\Security;

use RuntimeException;

class OutboundUrlGuard
{
    public function __construct(
        private readonly OutboundDnsResolver $dnsResolver,
    ) {}

    /**
     * @return array{url:string, host:string, port:int, address:string, options:array<string|int, mixed>}
     */
    public function validate(string $url): array
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('The catalog event destination is not a valid absolute URL.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Catalog event destinations cannot contain embedded credentials.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('The catalog event destination uses an unsupported URL scheme.');
        }
        if (app()->environment('production') && $scheme !== 'https') {
            throw new RuntimeException('Catalog event delivery requires HTTPS in production.');
        }

        $host = strtolower(rtrim(trim((string) $parts['host'], '[]'), '.'));
        if ($host === '') {
            throw new RuntimeException('The catalog event destination host is missing.');
        }

        $allowPrivateLocal = ! app()->environment('production')
            && (bool) config('connected_sites.allow_private_networks_non_production', false);
        $localName = $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test');
        if ($localName && ! $allowPrivateLocal) {
            throw new RuntimeException('Local catalog event destinations are disabled.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : $this->dnsResolver->resolve($host);
        if ($addresses === []) {
            throw new RuntimeException('The catalog event destination could not be resolved safely.');
        }

        foreach ($addresses as $address) {
            if (! $this->addressIsAllowed($address, $allowPrivateLocal)) {
                throw new RuntimeException('The catalog event destination resolves to a prohibited network address.');
            }
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('The catalog event destination port is invalid.');
        }

        $address = $addresses[0];
        $options = ['allow_redirects' => false];
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            if (! defined('CURLOPT_RESOLVE')) {
                if (app()->environment('production')) {
                    throw new RuntimeException('Safe DNS pinning is unavailable for catalog event delivery.');
                }
            } else {
                $pinnedAddress = str_contains($address, ':') ? '['.$address.']' : $address;
                $options['curl'] = [
                    CURLOPT_RESOLVE => [$host.':'.$port.':'.$pinnedAddress],
                ];
            }
        }

        return [
            'url' => $url,
            'host' => $host,
            'port' => $port,
            'address' => $address,
            'options' => $options,
        ];
    }

    private function addressIsAllowed(string $address, bool $allowPrivateLocal): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        if ($allowPrivateLocal && $this->isExplicitPrivateOrLoopback($address)) {
            return true;
        }

        if ($this->isSpecialUseAddress($address)) {
            return false;
        }

        return (bool) filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    private function isSpecialUseAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $value = ip2long($address);
            if ($value === false) {
                return true;
            }
            $value = (int) sprintf('%u', $value);

            foreach ([
                ['0.0.0.0', 8],
                ['10.0.0.0', 8],
                ['100.64.0.0', 10],
                ['127.0.0.0', 8],
                ['169.254.0.0', 16],
                ['172.16.0.0', 12],
                ['192.0.0.0', 24],
                ['192.0.2.0', 24],
                ['192.168.0.0', 16],
                ['198.18.0.0', 15],
                ['198.51.100.0', 24],
                ['203.0.113.0', 24],
                ['224.0.0.0', 4],
                ['240.0.0.0', 4],
            ] as [$network, $prefix]) {
                if ($this->inIpv4Range($value, $network, $prefix)) {
                    return true;
                }
            }

            return false;
        }

        $packed = @inet_pton($address);
        if (! is_string($packed) || strlen($packed) !== 16) {
            return true;
        }

        foreach ([
            ['::', 128],
            ['::1', 128],
            ['::ffff:0:0', 96],
            ['64:ff9b:1::', 48],
            ['100::', 64],
            ['2001:db8::', 32],
            ['fc00::', 7],
            ['fe80::', 10],
            ['ff00::', 8],
        ] as [$network, $prefix]) {
            if ($this->inIpv6Range($packed, $network, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isExplicitPrivateOrLoopback(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $value = ip2long($address);
            if ($value === false) {
                return false;
            }
            $value = (int) sprintf('%u', $value);

            return $this->inIpv4Range($value, '10.0.0.0', 8)
                || $this->inIpv4Range($value, '172.16.0.0', 12)
                || $this->inIpv4Range($value, '192.168.0.0', 16)
                || $this->inIpv4Range($value, '127.0.0.0', 8);
        }

        $packed = @inet_pton($address);
        if (! is_string($packed) || strlen($packed) !== 16) {
            return false;
        }

        if ($address === '::1') {
            return true;
        }

        $first = ord($packed[0]);

        return ($first & 0xfe) === 0xfc;
    }

    private function inIpv4Range(int $address, string $network, int $prefix): bool
    {
        $networkValue = ip2long($network);
        if ($networkValue === false) {
            return false;
        }

        $networkValue = (int) sprintf('%u', $networkValue);
        $mask = (0xffffffff << (32 - $prefix)) & 0xffffffff;

        return ($address & $mask) === ($networkValue & $mask);
    }

    private function inIpv6Range(string $address, string $network, int $prefix): bool
    {
        $networkBytes = @inet_pton($network);
        if (! is_string($networkBytes) || strlen($networkBytes) !== 16) {
            return false;
        }

        $wholeBytes = intdiv($prefix, 8);
        if ($wholeBytes > 0 && substr($address, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $prefix % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($address[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }
}
