<?php

declare(strict_types=1);

namespace Duxbo\Seo\Redirects;

use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Exceptions\UnsafeRedirect;
use Duxbo\Seo\Support\SameOriginUrls;

/**
 * Refuses to store a rule that would be dangerous or broken.
 *
 * None of these checks can be switched off. Each guards against a real
 * vulnerability rather than a matter of taste, and there is no legitimate use
 * for a redirect that sends visitors to an unapproved host or loops forever.
 */
final class RedirectGuard
{
    public function __construct(private readonly SameOriginUrls $sameOrigin)
    {
    }

    /**
     * @throws UnsafeRedirect
     */
    public function assertTargetIsSafe(?string $target): void
    {
        if ($target === null || $target === '') {
            return;
        }

        // A path stays on this site by definition. Protocol-relative "//evil.com"
        // looks like a path and is not one, so it is rejected explicitly.
        if (str_starts_with($target, '//')) {
            throw UnsafeRedirect::protocolRelative($target);
        }

        if (str_starts_with($target, '/')) {
            return;
        }

        $host = parse_url($target, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw UnsafeRedirect::unparseable($target);
        }

        if (! $this->sameOrigin->isAllowed($target)) {
            throw UnsafeRedirect::hostNotAllowed($host, $this->sameOrigin->allowedHosts());
        }
    }

    /**
     * @throws UnsafeRedirect
     */
    public function assertPatternIsSafe(RedirectMatchType $type, string $source): void
    {
        if ($type !== RedirectMatchType::Regex) {
            return;
        }

        // Nested quantifiers are the shape that makes a pattern take
        // exponential time on a crafted path, hanging the request.
        if (preg_match('/(\([^)]*[+*][^)]*\)|\[[^\]]*\])\s*[+*]{1,2}/', $source) === 1) {
            throw UnsafeRedirect::catastrophicPattern($source);
        }

        $valid = @preg_match($this->delimit($source), '');

        if ($valid === false) {
            throw UnsafeRedirect::invalidPattern($source);
        }
    }

    /**
     * Follow the chain from this rule and refuse a cycle.
     *
     * A → B → A takes the site down for every visitor on those paths, and the
     * error surfaces as a browser redirect loop with nothing in the logs.
     *
     * @param  callable(string): ?string  $resolve  Path to its redirect target.
     *
     * @throws UnsafeRedirect
     */
    public function assertNoLoop(string $source, ?string $target, callable $resolve, int $maxDepth = 10): void
    {
        if ($target === null) {
            return;
        }

        $seen = [$this->normalise($source)];
        $current = $target;

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            $path = $this->pathOf($current);

            if ($path === null) {
                return;
            }

            $normalised = $this->normalise($path);

            if (in_array($normalised, $seen, true)) {
                throw UnsafeRedirect::loop([...$seen, $normalised]);
            }

            $seen[] = $normalised;
            $next = $resolve($path);

            if ($next === null) {
                return;
            }

            $current = $next;
        }

        throw UnsafeRedirect::chainTooLong($seen);
    }

    public function delimit(string $pattern): string
    {
        // Already delimited by the author — respect it.
        if (preg_match('/^([#~\/]).*\1[imsuxADSUXJ]*$/', $pattern) === 1) {
            return $pattern;
        }

        return '#'.str_replace('#', '\#', $pattern).'#';
    }

    public function normalise(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function pathOf(string $target): ?string
    {
        if (str_starts_with($target, '/')) {
            return $target;
        }

        $host = parse_url($target, PHP_URL_HOST);

        // An off-site target ends the chain; it cannot loop back here.
        if (is_string($host) && ! $this->sameOrigin->isAllowed($target)) {
            return null;
        }

        $path = parse_url($target, PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }
}
