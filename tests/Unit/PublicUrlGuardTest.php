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
}
