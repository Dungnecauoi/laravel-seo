<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

/**
 * Implemented by a singleton that caches something in one of its own
 * instance properties, rather than only in Laravel's shared `Cache` store.
 *
 * Under ordinary PHP-FPM that distinction never matters: the whole
 * container, singletons included, is rebuilt fresh on every request, so an
 * instance property cannot outlive the request that set it. Under a
 * long-running worker (Laravel Octane, or any runtime that keeps the
 * application booted across requests) the same singleton instance persists
 * for the worker's entire life, and an instance property genuinely does not
 * know a new request has started — it takes an explicit signal to drop it.
 *
 * Optional, not part of any domain contract: a custom `RedirectMatcher` or
 * `AiDriver` a project supplies has no reason to implement this unless it
 * has the same kind of state to worry about.
 */
interface ResetsBetweenRequests
{
    public function resetForNewRequest(): void;
}
