<?php

declare(strict_types=1);

namespace Duxbo\Seo\BrokenLinks;

/**
 * Decides whether a URL is safe for this server to fetch.
 *
 * The URLs {@see BrokenLinkChecker} requests come from a model's own
 * content — written by whoever authors that record, not by whoever runs
 * `seo:broken-links` or holds the Gate ability behind its AI tool. Without
 * this check, a lower-trust content author could embed a link to
 * `http://169.254.169.254/...` (a cloud metadata endpoint) or an internal
 * service, and a scheduled crawl would turn this server into an SSRF
 * probe against its own network, with the resulting HTTP status readable
 * back through the panel and API.
 *
 * Checked before every request the checker makes, including after each
 * redirect hop — a public URL can answer with a 302 to an internal one,
 * and Laravel's HTTP client follows redirects by default.
 *
 * A resolved-but-attacker-controlled DNS record served with a very short
 * TTL could still in principle answer differently between this check and
 * the HTTP client's own connection (DNS rebinding); pinning the exact IP
 * the request connects to would close that gap but needs low-level cURL
 * options tied to the Host header. What this class does close is the
 * realistic case this feature exists to guard against: a literal internal
 * address or a hostname that resolves to one.
 */
final class PublicUrlGuard
{
    /** @var callable(string): list<string> */
    private $resolver;

    /**
     * @param  (callable(string): list<string>)|null  $resolver  Returns every
     *     IP address a hostname resolves to. Defaults to a real DNS lookup;
     *     overridable so tests never depend on live network DNS.
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?? self::systemResolver();
    }

    public function allows(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return false;
        }

        // parse_url() keeps the brackets on an IPv6 literal host
        // ("[::1]") — filter_var() needs them stripped to recognise it as
        // an IP at all, otherwise this falls through to DNS resolution of
        // the literal string "[::1]", which fails and blocks even a
        // genuinely public IPv6 address.
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host);
        }

        $ips = ($this->resolver)($host);

        if ($ips === []) {
            return false;
        }

        // A hostname is only trusted once every address it resolves to is
        // public — one private/loopback record is enough to block it. A
        // hostname legitimately answering with both a public and a private
        // record is not a case this package needs to support; treating it
        // as untrusted is the safe default.
        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * @return callable(string): list<string>
     */
    private static function systemResolver(): callable
    {
        return static function (string $host): array {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);

            if ($records === false) {
                return [];
            }

            $ips = array_map(
                static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
                $records,
            );

            return array_values(array_filter($ips, static fn (?string $ip): bool => $ip !== null));
        };
    }
}
