<?php

declare(strict_types=1);

namespace Duxbo\Seo\Canonical;

use Duxbo\Seo\Contracts\CanonicalResolver;
use Duxbo\Seo\Exceptions\UnsafeCanonical;

/**
 * Refuses to store a canonical that would form a cycle across records —
 * mirrors {@see \Duxbo\Seo\Redirects\RedirectGuard::assertNoLoop()}, with one
 * structural difference a redirect rule never has to deal with: a page
 * canonicalizing to *itself* is not a bug, it is the overwhelmingly common,
 * correct case, so that is checked for and allowed before anything is
 * treated as a chain to walk.
 */
final class CanonicalGuard
{
    public function __construct(private readonly CanonicalResolver $resolver)
    {
    }

    /**
     * @throws UnsafeCanonical
     */
    public function assertNoCycle(string $subjectUrl, ?string $canonical, int $maxDepth = 10): void
    {
        if ($canonical === null) {
            return;
        }

        $subject = $this->normalise($subjectUrl);
        $target = $this->normalise($canonical);

        if ($target === $subject) {
            return;
        }

        $seen = [$subject];
        $current = $canonical;

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            $normalised = $this->normalise($current);

            if (in_array($normalised, $seen, true)) {
                throw UnsafeCanonical::cycle([...$seen, $normalised]);
            }

            $seen[] = $normalised;
            $next = $this->resolver->resolve($current);

            // No further hop known, or the target's own canonical simply
            // points back at itself: the chain ends here, safely.
            if ($next === null || $this->normalise($next) === $normalised) {
                return;
            }

            $current = $next;
        }

        throw UnsafeCanonical::chainTooLong($seen);
    }

    private function normalise(string $url): string
    {
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = $path === '' ? '/' : $path;
        $path = rtrim($path, '/');
        $path = $path === '' ? '/' : $path;
        $query = parse_url($url, PHP_URL_QUERY);

        return $scheme.'://'.$host.$path.($query !== null && $query !== '' ? '?'.$query : '');
    }
}
