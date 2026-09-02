<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\RedirectMatch;

/**
 * Finds the redirect rule, if any, that claims an incoming path.
 *
 * The default implementation loads active rules into cache once and evaluates
 * them cheapest-first: exact matches by hash, then prefixes, then patterns.
 */
interface RedirectMatcher
{
    /**
     * @param  string  $path  Request path without host, with leading slash.
     */
    public function match(string $path, ?string $locale = null): ?RedirectMatch;

    /**
     * Drop any cached rule set. Called after a rule is written.
     */
    public function flush(): void;
}
