<?php

declare(strict_types=1);

namespace Recipes\Http;

use Closure;

/**
 * Fetches user-supplied URLs without letting them reach internal services.
 *
 * Every hop (including redirects) must be http(s) on the default port and
 * resolve only to public addresses. The connection is pinned to the vetted
 * IP with CURLOPT_RESOLVE, so a second DNS lookup cannot swap in a private
 * address (DNS rebinding). Responses are capped at a maximum size.
 */
class SafeHttpClient
{
    private const MAX_REDIRECTS = 5;
    private const TOTAL_TIMEOUT = 20;
    private const USER_AGENT = 'Mozilla/5.0 (compatible; RecipeImporter/1.0)';

    /** IPv4 ranges that are never fetched: private, loopback, link-local, CGNAT, reserved, multicast. */
    private const BLOCKED_V4 = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16',
        '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];

    /** IPv6 blocks inside global unicast (2000::/3) that tunnel, embed IPv4, or are reserved. */
    private const BLOCKED_V6 = ['2001::/23', '2001:db8::/32', '2002::/16'];

    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /**
     * @param (callable(string): list<string>)|null $resolver Maps a hostname to its IP addresses.
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver !== null
            ? Closure::fromCallable($resolver)
            : Closure::fromCallable([self::class, 'resolveWithDns']);
    }

    /**
     * Fetch a URL. Returns null if the URL (or any redirect) is not allowed,
     * the request fails, the status is not 2xx, or the body exceeds $maxBytes.
     *
     * @return array{body: string, contentType: string}|null
     */
    public function fetch(string $url, int $maxBytes): ?array
    {
        $deadline = time() + self::TOTAL_TIMEOUT;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->resolveTarget($url);
            $remaining = $deadline - time();
            if ($target === null || $remaining < 1) {
                return null;
            }

            $response = $this->request($url, $target, $maxBytes, $remaining);
            if ($response === null) {
                return null;
            }

            if ($response['status'] >= 300 && $response['status'] < 400 && $response['location'] !== '') {
                $url = self::resolveRelative($url, $response['location']);
                continue;
            }

            if ($response['status'] < 200 || $response['status'] >= 300) {
                return null;
            }
            return ['body' => $response['body'], 'contentType' => $response['contentType']];
        }
        return null;
    }

    /**
     * Check a URL and pick the public IP to connect to.
     *
     * @return array{host: string, port: int, ip: string}|null
     */
    public function resolveTarget(string $url): ?array
    {
        // Refuse anything PHP and curl might parse differently (backslashes,
        // whitespace, control characters), so the host vetted here is the
        // host curl connects to.
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;
        $port = $parts['port'] ?? $defaultPort;
        if ($port !== $defaultPort) {
            return null;
        }

        $host = strtolower(trim($parts['host'], '[]'));
        if (filter_var($host, FILTER_VALIDATE_IP) === false && preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            return null;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);
        if ($ips === []) {
            return null;
        }
        // Every answer must be public; otherwise a host could list one public
        // and one private address and let curl pick.
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return null;
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /**
     * True only for globally routable unicast addresses.
     */
    public static function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            foreach (self::BLOCKED_V4 as $cidr) {
                if (self::inCidr($packed, $cidr)) {
                    return false;
                }
            }
            return true;
        }

        // IPv6: allow only global unicast. This also rejects IPv4-mapped
        // (::ffff:0:0/96) and NAT64 (64:ff9b::/96) forms of internal IPv4.
        if (!self::inCidr($packed, '2000::/3')) {
            return false;
        }
        foreach (self::BLOCKED_V6 as $cidr) {
            if (self::inCidr($packed, $cidr)) {
                return false;
            }
        }
        return true;
    }

    private static function inCidr(string $packedIp, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $packedNet = inet_pton($network);
        if ($packedNet === false || strlen($packedNet) !== strlen($packedIp)) {
            return false;
        }
        $bits = (int)$bits;
        $bytes = intdiv($bits, 8);
        if (substr($packedIp, 0, $bytes) !== substr($packedNet, 0, $bytes)) {
            return false;
        }
        $rem = $bits % 8;
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        return (ord($packedIp[$bytes]) & $mask) === (ord($packedNet[$bytes]) & $mask);
    }

    /**
     * @return list<string>
     */
    private static function resolveWithDns(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($aaaa as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        return array_values(array_unique($ips));
    }

    /**
     * @param array{host: string, port: int, ip: string} $target
     * @return array{status: int, location: string, contentType: string, body: string}|null
     */
    private function request(string $url, array $target, int $maxBytes, int $timeout): ?array
    {
        $body = '';
        $tooLarge = false;
        $location = '';
        $pinnedIp = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RESOLVE        => [$target['host'] . ':' . $target['port'] . ':' . $pinnedIp],
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY          => '',
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADERFUNCTION => function ($ch, string $header) use (&$location): int {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION  => function ($ch, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;
                    return 0; // abort the transfer
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($ok === false || $tooLarge) {
            return null;
        }
        return ['status' => $status, 'location' => $location, 'contentType' => $contentType, 'body' => $body];
    }

    /**
     * Resolve a redirect Location against the URL that returned it.
     * The result is re-vetted by resolveTarget() before it is fetched.
     */
    public static function resolveRelative(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
            return $location;
        }
        $parts = parse_url($base) ?: [];
        $scheme = $parts['scheme'] ?? 'http';
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }
        $origin = $scheme . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path = $parts['path'] ?? '/';
        if (str_starts_with($location, '?')) {
            return $origin . $path . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $dir = substr($path, 0, (int)strrpos($path, '/') + 1);
        return $origin . ($dir === '' ? '/' : $dir) . $location;
    }
}
