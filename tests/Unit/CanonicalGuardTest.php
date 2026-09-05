<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\Canonical\CanonicalGuard;
use Duxbo\Seo\Contracts\CanonicalResolver;
use Duxbo\Seo\Exceptions\UnsafeCanonical;
use PHPUnit\Framework\TestCase;

final class CanonicalGuardTest extends TestCase
{
    public function test_a_null_canonical_is_always_allowed(): void
    {
        $guard = $this->guard([]);

        $guard->assertNoCycle('https://x.test/a', null);

        $this->addToAssertionCount(1);
    }

    public function test_a_page_canonicalizing_to_itself_is_allowed_without_asking_the_resolver(): void
    {
        // A resolver that throws proves the self-reference short-circuit
        // never even calls it.
        $guard = new CanonicalGuard(new class implements CanonicalResolver {
            public function resolve(string $url): ?string
            {
                throw new \LogicException('should not be called');
            }
        });

        $guard->assertNoCycle('https://x.test/a', 'https://x.test/a');

        $this->addToAssertionCount(1);
    }

    public function test_a_chain_that_terminates_is_allowed(): void
    {
        $guard = $this->guard([
            'https://x.test/b' => 'https://x.test/c',
            'https://x.test/c' => 'https://x.test/c',
        ]);

        $guard->assertNoCycle('https://x.test/a', 'https://x.test/b');

        $this->addToAssertionCount(1);
    }

    public function test_a_two_hop_cycle_is_refused(): void
    {
        $guard = $this->guard([
            'https://x.test/b' => 'https://x.test/a',
        ]);

        $this->expectException(UnsafeCanonical::class);
        $this->expectExceptionMessageMatches('/cycle/');

        $guard->assertNoCycle('https://x.test/a', 'https://x.test/b');
    }

    public function test_a_chain_longer_than_max_depth_is_refused(): void
    {
        $map = [];

        for ($i = 1; $i <= 12; $i++) {
            $map["https://x.test/{$i}"] = 'https://x.test/'.($i + 1);
        }

        $guard = $this->guard($map);

        $this->expectException(UnsafeCanonical::class);
        $this->expectExceptionMessageMatches('/longer than/');

        $guard->assertNoCycle('https://x.test/0', 'https://x.test/1');
    }

    public function test_normalisation_ignores_a_trailing_slash_and_scheme_host_case(): void
    {
        $guard = $this->guard([
            'https://x.test/b' => 'HTTPS://X.TEST/a/',
        ]);

        $this->expectException(UnsafeCanonical::class);

        $guard->assertNoCycle('https://x.test/a', 'https://x.test/b');
    }

    /**
     * @param  array<string, string>  $map
     */
    private function guard(array $map): CanonicalGuard
    {
        return new CanonicalGuard(new class($map) implements CanonicalResolver {
            /** @param array<string, string> $map */
            public function __construct(private readonly array $map)
            {
            }

            public function resolve(string $url): ?string
            {
                return $this->map[$url] ?? null;
            }
        });
    }
}
