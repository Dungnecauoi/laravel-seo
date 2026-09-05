<?php

declare(strict_types=1);

namespace Duxbo\Seo\Canonical;

use Duxbo\Seo\Contracts\CanonicalResolver;

/**
 * The default binding. Always answers "I don't know," which makes
 * {@see CanonicalGuard::assertNoCycle()} a safe no-op until an application
 * binds a resolver that can actually walk its own URLs.
 */
final class NullCanonicalResolver implements CanonicalResolver
{
    public function resolve(string $url): ?string
    {
        return null;
    }
}
