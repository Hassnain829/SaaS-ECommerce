<?php

namespace Tests\Unit;

use App\Services\Security\OutboundDnsResolver;
use App\Services\Security\OutboundUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OutboundUrlGuardTest extends TestCase
{
    #[DataProvider('prohibitedProductionDestinationProvider')]
    public function test_production_rejects_prohibited_destinations(string $url, array $addresses): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $guard = new OutboundUrlGuard($this->resolver($addresses));

            $this->expectException(RuntimeException::class);
            $guard->validate($url);
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public static function prohibitedProductionDestinationProvider(): array
    {
        return [
            'plain HTTP' => ['http://public.example.com/events', ['93.184.216.34']],
            'userinfo' => ['https://user:secret@public.example.com/events', ['93.184.216.34']],
            'localhost name' => ['https://localhost/events', ['127.0.0.1']],
            'dot-local name' => ['https://shop.local/events', ['93.184.216.34']],
            'dot-test name' => ['https://shop.test/events', ['93.184.216.34']],
            'private IPv4' => ['https://private.example.com/events', ['10.0.0.8']],
            'loopback IPv4' => ['https://loopback.example.com/events', ['127.0.0.1']],
            'link-local IPv4' => ['https://linklocal.example.com/events', ['169.254.169.254']],
            'multicast IPv4' => ['https://multicast.example.com/events', ['224.0.0.1']],
            'unspecified IPv4' => ['https://unspecified.example.com/events', ['0.0.0.0']],
            'private IPv6' => ['https://private-v6.example.com/events', ['fd00::1']],
            'loopback IPv6' => ['https://loopback-v6.example.com/events', ['::1']],
            'link-local IPv6' => ['https://linklocal-v6.example.com/events', ['fe80::1']],
            'multicast IPv6' => ['https://multicast-v6.example.com/events', ['ff02::1']],
            'unspecified IPv6' => ['https://unspecified-v6.example.com/events', ['::']],
            'mixed public and private DNS answers' => ['https://rebind.example.com/events', ['93.184.216.34', '127.0.0.1']],
        ];
    }

    public function test_public_destination_disables_redirects_and_pins_the_validated_dns_answer(): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $validated = (new OutboundUrlGuard($this->resolver(['93.184.216.34'])))
                ->validate('https://public.example.com/wp-json/eco-portal/v1/events');

            $this->assertFalse($validated['options']['allow_redirects']);
            $this->assertSame('93.184.216.34', $validated['address']);
            if (defined('CURLOPT_RESOLVE')) {
                $this->assertSame(
                    ['public.example.com:443:93.184.216.34'],
                    $validated['options']['curl'][CURLOPT_RESOLVE]
                );
            }
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    private function resolver(array $addresses): OutboundDnsResolver
    {
        return new class($addresses) extends OutboundDnsResolver
        {
            public function __construct(private readonly array $addresses) {}

            public function resolve(string $host): array
            {
                return $this->addresses;
            }
        };
    }
}
