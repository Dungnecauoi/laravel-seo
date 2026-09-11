<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\BrokenLinks\PublicUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicUrlGuardTest extends TestCase
{
    #[DataProvider('blockedLiteralIpProvider')]
    public function test_blocks_a_literal_private_loopback_or_link_local_ip(string $url): void
    {
        // The resolver answers with a public IP no matter what it's asked —
        // proving these are blocked by recognising the literal IP itself,
        // not by accidentally falling through to a failed DNS lookup that
        // happens to also block them for the wrong reason.
        $guard = new PublicUrlGuard(static fn (): array => ['1.1.1.1']);

        $this->assertFalse($guard->allows($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedLiteralIpProvider(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1/'];
        yield 'cloud metadata endpoint' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'private class A' => ['http://10.0.0.5/'];
        yield 'private class B' => ['http://172.16.0.1/'];
        yield 'private class C' => ['http://192.168.1.1/'];
        yield 'ipv6 loopback' => ['http://[::1]/'];
    }

    public function test_allows_a_literal_public_ip(): void
    {
        $guard = new PublicUrlGuard(static fn (): array => []);

        $this->assertTrue($guard->allows('http://1.1.1.1/'));
    }

    public function test_allows_a_literal_public_ipv6_address(): void
    {
        // Regression: parse_url() keeps the brackets on an IPv6 host, and
        // filter_var() rejects "[2606:4700:4700::1111]" as-is — without
        // stripping them first, every bracketed IPv6 literal falls through
        // to DNS resolution of that literal string and is wrongly blocked,
        // public or not. A resolver that would fail this hostname lookup
        // proves the literal-IP branch is what actually decided this.
        $guard = new PublicUrlGuard(static fn (): array => []);

        $this->assertTrue($guard->allows('http://[2606:4700:4700::1111]/'));
    }

    public function test_rejects_a_non_http_scheme(): void
    {
        $guard = new PublicUrlGuard(static fn (): array => ['1.1.1.1']);

        $this->assertFalse($guard->allows('file:///etc/passwd'));
        $this->assertFalse($guard->allows('ftp://vidu.vn/x'));
    }

    public function test_rejects_a_url_with_no_host(): void
    {
        $guard = new PublicUrlGuard(static fn (): array => ['1.1.1.1']);

        $this->assertFalse($guard->allows('not-a-url'));
    }

    public function test_allows_a_hostname_that_resolves_only_to_public_addresses(): void
    {
        $guard = new PublicUrlGuard(static fn (string $host): array => $host === 'vidu.vn' ? ['93.184.216.34'] : []);

        $this->assertTrue($guard->allows('https://vidu.vn/trang'));
    }

    public function test_blocks_a_hostname_that_resolves_to_any_private_address(): void
    {
        // A hostname answering with both a public and a private record — one
        // bad record is enough to block the whole hostname.
        $guard = new PublicUrlGuard(static fn (): array => ['93.184.216.34', '127.0.0.1']);

        $this->assertFalse($guard->allows('https://noi-bo-gia.com/'));
    }

    public function test_blocks_a_hostname_that_fails_to_resolve(): void
    {
        $guard = new PublicUrlGuard(static fn (): array => []);

        $this->assertFalse($guard->allows('https://khong-ton-tai-doi-tuong.invalid/'));
    }

    #[DataProvider('obfuscatedIpLiteralProvider')]
    public function test_blocks_an_obfuscated_ip_literal_a_resolver_would_wrongly_treat_as_a_hostname(string $url): void
    {
        // filter_var(FILTER_VALIDATE_IP) does not recognise decimal-integer,
        // octal, or hex IP notation as an IP at all, so without a dedicated
        // check these fall through to DNS resolution as a "hostname" —
        // finding nothing, and looking safe purely because this resolver
        // has no opinion on a string that isn't a real domain. The real
        // HTTP client's underlying cURL/libc resolver parses the identical
        // string with C-style numeral semantics and connects to loopback
        // regardless. The resolver here answers with a public IP no matter
        // what it's asked, proving a block happens before DNS resolution
        // is ever consulted, not because it coincidentally also blocks these.
        $guard = new PublicUrlGuard(static fn (): array => ['1.1.1.1']);

        $this->assertFalse($guard->allows($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function obfuscatedIpLiteralProvider(): iterable
    {
        // 127.0.0.1 written as octal (leading zero on each octet).
        yield 'octal loopback' => ['http://0177.0.0.1/'];
        yield 'octal loopback, fully zero-padded' => ['http://0177.0000.0000.0001/'];
        // 127.0.0.1 as a single decimal integer.
        yield 'decimal-integer loopback' => ['http://2130706433/'];
        // 127.0.0.1 in hex.
        yield 'hex loopback' => ['http://0x7f000001/'];
    }

    public function test_a_real_hostname_shaped_like_only_digits_and_dots_is_never_mistaken_for_an_ip_literal(): void
    {
        // No real TLD is all-numeric, so this case can't occur with a
        // genuine hostname — but the block-on-shape check must never fire
        // for a resolver-confirmed-public literal IP itself.
        $guard = new PublicUrlGuard(static fn (): array => ['1.1.1.1']);

        $this->assertTrue($guard->allows('http://1.1.1.1/'));
    }

    public function test_resolve_returns_the_approved_ip_for_a_literal_ip_url(): void
    {
        $guard = new PublicUrlGuard(static fn (): array => []);

        $this->assertSame('1.1.1.1', $guard->resolve('http://1.1.1.1/'));
    }

    public function test_resolve_returns_the_first_resolved_ip_for_a_hostname(): void
    {
        $guard = new PublicUrlGuard(static fn (): array => ['93.184.216.34']);

        $this->assertSame('93.184.216.34', $guard->resolve('https://vidu.vn/'));
    }

    public function test_resolve_returns_null_for_a_blocked_url(): void
    {
        $guard = new PublicUrlGuard(static fn (): array => []);

        $this->assertNull($guard->resolve('http://127.0.0.1/'));
    }
}
