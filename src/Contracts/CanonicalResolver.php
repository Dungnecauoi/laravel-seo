<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

/**
 * Given a canonical target URL, returns whatever *that* URL's own stored
 * canonical points to, so {@see \Duxbo\Seo\Canonical\CanonicalGuard} can walk
 * the chain past the record currently being saved and catch A → B → A before
 * it reaches search engines as two pages endorsing each other in a circle.
 *
 * Unlike a redirect rule, which lives in a table this package owns end to
 * end, a canonical target is an arbitrary URL string with no guaranteed way
 * back to the {@see Seoable} record (if any) that owns it — so the default
 * binding, {@see \Duxbo\Seo\Canonical\NullCanonicalResolver}, always returns
 * null, which makes the cycle check a safe no-op. An application that already
 * knows how to map one of its own URLs back to a record can bind a real
 * implementation here to turn the check on.
 */
interface CanonicalResolver
{
    public function resolve(string $url): ?string;
}
