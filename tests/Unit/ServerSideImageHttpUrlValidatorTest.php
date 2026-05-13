<?php

namespace Tests\Unit;

use App\Support\Security\ServerSideImageHttpUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServerSideImageHttpUrlValidatorTest extends TestCase
{
    public function test_rejects_invalid_scheme(): void
    {
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('ftp://1.1.1.1/file.png'));
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('file:///etc/passwd'));
    }

    public function test_rejects_localhost_literal(): void
    {
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('http://127.0.0.1/image.png'));
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('http://localhost/image.png'));
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('http://[::1]/image.png'));
    }

    public function test_rejects_private_ipv4(): void
    {
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('http://10.0.0.1/x.png'));
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('http://192.168.1.1/x.png'));
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('http://172.16.0.1/x.png'));
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('http://169.254.1.1/x.png'));
    }

    public function test_rejects_private_ipv6(): void
    {
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('http://[fc00::1]/x.png'));
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('http://[fe80::1]/x.png'));
    }

    #[DataProvider('publicIpImageUrlProvider')]
    public function test_allows_public_ip_literal_urls(string $url): void
    {
        $this->assertTrue(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl($url));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function publicIpImageUrlProvider(): iterable
    {
        yield 'cloudflare-dns' => ['https://1.1.1.1/favicon.ico'];
        yield 'quad9' => ['https://9.9.9.9/'];
    }

    public function test_allows_resolved_public_host_when_dns_returns_public_ip(): void
    {
        $ips = @gethostbynamel('one.one.one.one') ?: [];
        if ($ips === []) {
            $this->markTestSkipped('DNS resolution for one.one.one.one unavailable in this environment.');
        }
        $allPublic = true;
        foreach ($ips as $ip) {
            if (! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                $allPublic = false;
                break;
            }
        }
        if (! $allPublic) {
            $this->markTestSkipped('one.one.one.one did not resolve to a public IPv4 address in this environment.');
        }

        $this->assertTrue(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('https://one.one.one.one/favicon.ico'));
    }

    public function test_rejects_urls_with_credentials(): void
    {
        $this->assertFalse(ServerSideImageHttpUrlValidator::isSafeRemoteHttpUrl('https://user:pass@1.1.1.1/x.png'));
    }
}
