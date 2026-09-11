<?php

declare(strict_types=1);

namespace Duxbo\Seo\BrokenLinks;

/**
 * Decides whether a URL is safe for this server to fetch, and which exact
 * IP address to fetch it from.
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
 * Two things this class deliberately does NOT leave to a second, separate
 * resolution by the HTTP client itself:
 *
 * - A host written as a decimal-integer, octal, or hex IP literal
 *   ("2130706433", "0177.0.0.1", "0x7f000001") is not recognised by
 *   `filter_var(..., FILTER_VALIDATE_IP)` as an IP at all, so without a
 *   dedicated check it falls through to DNS resolution as if it were a
 *   hostname — finds nothing, and looks "safe" purely because the guard's
 *   own resolver has no opinion on it. But the HTTP client's underlying
 *   resolver (cURL/libc) parses the identical string using C-style numeral
 *   semantics and connects to whatever address that decodes to, which can
 *   silently disagree with what this class checked. `looksLikeIpLiteral()`
 *   refuses every host built only from digits/dots/an "0x" hex prefix
 *   outright — no real DNS hostname is shaped like that (no TLD is
 *   all-numeric or a bare hex string), so this never blocks a genuine one.
 * - `resolve()` returns the exact IP this class approved, and
 *   {@see BrokenLinkChecker} pins the real request to that literal address
 *   (via cURL's `CURLOPT_RESOLVE`) rather than letting the HTTP client
 *   resolve the hostname a second time. Resolving twice is a DNS-rebinding
 *   gap: an attacker controlling authoritative DNS for the hostname, at a
 *   very short TTL, can answer publicly for this check and privately for
 *   the real connection moments later. Pinning closes that regardless of
 *   how fast the record changes.
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
        return $this->resolve($url) !== null;
    }

    /**
     * The single IP address it is safe to actually connect to for this URL,
     * or null if the URL is blocked. Callers MUST pin the real request to
     * exactly this address — see this class's own docblock for why a
     * second, independent resolution is not safe to rely on.
     */
    public function resolve(string $url): ?string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return null;
        }

        // parse_url() keeps the brackets on an IPv6 literal host
        // ("[::1]") — filter_var() needs them stripped to recognise it as
        // an IP at all, otherwise this falls through to DNS resolution of
        // the literal string "[::1]", which fails and blocks even a
        // genuinely public IPv6 address.
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host) ? $host : null;
        }

        if ($this->looksLikeIpLiteral($host)) {
            return null;
        }

        $ips = ($this->resolver)($host);

        if ($ips === []) {
            return null;
        }

        // A hostname is only trusted once every address it resolves to is
        // public — one private/loopback record is enough to block it. A
        // hostname legitimately answering with both a public and a private
        // record is not a case this package needs to support; treating it
        // as untrusted is the safe default.
        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return null;
            }
        }

        return $ips[0];
    }

    /**
     * A real DNS hostname always contains a letter outside a bare "0x"
     * prefix (no TLD is all-digits, and none is a plain hex string), so
     * refusing anything built only from digits, dots, and an "0x" prefix
     * never blocks a genuine hostname — only the decimal/octal/hex IP
     * notations a C-style resolver accepts but filter_var() does not.
     */
    private function looksLikeIpLiteral(string $host): bool
    {
        return preg_match('/^0x[0-9a-f]+$/i', $host) === 1
            || preg_match('/^[0-9.]+$/', $host) === 1;
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
