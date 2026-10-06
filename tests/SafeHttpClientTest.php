<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Recipes\Http\SafeHttpClient;

class SafeHttpClientTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function ipProvider(): array
    {
        return [
            'public v4'          => ['93.184.216.34', true],
            'public v6'          => ['2606:4700::1111', true],
            'loopback'           => ['127.0.0.1', false],
            'loopback range'     => ['127.8.8.8', false],
            'rfc1918 10'         => ['10.1.2.3', false],
            'rfc1918 172'        => ['172.20.0.1', false],
            'rfc1918 192'        => ['192.168.1.1', false],
            'link-local / cloud metadata' => ['169.254.169.254', false],
            'carrier-grade nat'  => ['100.64.0.1', false],
            'unspecified'        => ['0.0.0.0', false],
            'benchmark'          => ['198.18.0.1', false],
            'multicast'          => ['224.0.0.1', false],
            'broadcast'          => ['255.255.255.255', false],
            'v6 loopback'        => ['::1', false],
            'v6 unique local'    => ['fd00::1', false],
            'v6 link-local'      => ['fe80::1', false],
            'v4-mapped loopback' => ['::ffff:127.0.0.1', false],
            'v4-mapped hex'      => ['::ffff:7f00:1', false],
            'nat64 loopback'     => ['64:ff9b::7f00:1', false],
            '6to4'               => ['2002:7f00:1::1', false],
            'teredo'             => ['2001:0:4136:e378::1', false],
            'documentation v6'   => ['2001:db8::1', false],
            'not an ip'          => ['example.com', false],
        ];
    }

    #[DataProvider('ipProvider')]
    public function testIsPublicIp(string $ip, bool $expected): void
    {
        $this->assertSame($expected, SafeHttpClient::isPublicIp($ip));
    }

    /**
     * @param array<string, list<string>> $dns
     */
    private static function clientWithDns(array $dns): SafeHttpClient
    {
        return new SafeHttpClient(fn(string $host): array => $dns[$host] ?? []);
    }

    public function testResolveTargetAcceptsPublicHost(): void
    {
        $client = self::clientWithDns(['recipes.example' => ['93.184.216.34']]);
        $this->assertSame(
            ['host' => 'recipes.example', 'port' => 443, 'ip' => '93.184.216.34'],
            $client->resolveTarget('https://recipes.example/pumpkin/')
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedUrlProvider(): array
    {
        return [
            'private literal'     => ['http://192.168.1.10/admin'],
            'metadata literal'    => ['http://169.254.169.254/latest/meta-data/'],
            'bracketed v6'        => ['http://[::1]/'],
            'decimal ip host'     => ['http://2130706433/'],
            'name to private ip'  => ['http://intranet.example/'],
            'mixed answers'       => ['http://rebind.example/'],
            'unresolvable'        => ['http://nxdomain.example/'],
            'non-standard port'   => ['http://recipes.example:22/'],
            'file scheme'         => ['file:///etc/passwd'],
            'credentials in url'  => ['http://user:pass@recipes.example/'],
            'backslash authority' => ['http://recipes.example\\@10.0.0.5/'],
            'encoded host'        => ['http://recipes%2eexample/'],
            'whitespace'          => ["http://recipes.example /"],
            'control char'        => ["http://recipes.example/\x00"],
        ];
    }

    #[DataProvider('blockedUrlProvider')]
    public function testResolveTargetRejects(string $url): void
    {
        $client = self::clientWithDns([
            'recipes.example'  => ['93.184.216.34'],
            'intranet.example' => ['10.0.0.5'],
            'rebind.example'   => ['93.184.216.34', '127.0.0.1'],
        ]);
        $this->assertNull($client->resolveTarget($url));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function redirectProvider(): array
    {
        return [
            'absolute'           => ['https://a.example/x/y', 'https://b.example/z', 'https://b.example/z'],
            'protocol relative'  => ['https://a.example/x/y', '//b.example/z', 'https://b.example/z'],
            'root relative'      => ['https://a.example/x/y', '/z', 'https://a.example/z'],
            'relative from file' => ['https://a.example/x/y', 'z', 'https://a.example/x/z'],
            'relative from dir'  => ['https://a.example/x/y/', 'z', 'https://a.example/x/y/z'],
            'query only'         => ['https://a.example/x/y?a=1', '?b=2', 'https://a.example/x/y?b=2'],
        ];
    }

    #[DataProvider('redirectProvider')]
    public function testResolveRelative(string $base, string $location, string $expected): void
    {
        $this->assertSame($expected, SafeHttpClient::resolveRelative($base, $location));
    }

    public function testFetchRefusesPrivateTargetWithoutConnecting(): void
    {
        $client = self::clientWithDns(['intranet.example' => ['10.0.0.5']]);
        $this->assertNull($client->fetch('http://intranet.example/', 1024));
    }
}
