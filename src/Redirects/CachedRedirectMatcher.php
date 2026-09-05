<?php

declare(strict_types=1);

namespace Duxbo\Seo\Redirects;

use Duxbo\Seo\Contracts\RedirectMatcher;
use Duxbo\Seo\Contracts\ResetsBetweenRequests;
use Duxbo\Seo\Data\RedirectMatch;
use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Enums\RedirectType;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Loads every active rule once and matches in memory.
 *
 * Rules are few and paths are many, so the whole set is cached rather than
 * queried per request. Matching runs cheapest first: exact by hash lookup,
 * then longest-prefix, then patterns — so the expensive comparison only
 * happens for paths nothing else claimed.
 *
 * `$rules` is this class's *own* shortcut on top of the shared `Cache`
 * store `flush()` already invalidates correctly — under a long-running
 * worker this instance persists across requests, and a write from another
 * worker's process calls that same `flush()` on a *different* instance,
 * leaving this one still holding rules from before the edit. {@see
 * resetForNewRequest()} exists for exactly that: drop only the local
 * shortcut, so the next lookup re-checks the shared store, which the write
 * already invalidated correctly regardless of which process made it.
 */
final class CachedRedirectMatcher implements RedirectMatcher, ResetsBetweenRequests
{
    private const CACHE_KEY = 'seo:redirects';

    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $rules = null;

    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
        private readonly RedirectGuard $guard,
    ) {
    }

    public function match(string $path, ?string $locale = null): ?RedirectMatch
    {
        $path = $this->guard->normalise($path);
        $rules = $this->rules();

        return $this->matchExact($rules, $path, $locale)
            ?? $this->matchPrefix($rules, $path, $locale)
            ?? $this->matchRegex($rules, $path, $locale);
    }

    public function flush(): void
    {
        $this->rules = null;
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Unlike {@see flush()}, does not touch the shared cache — only the
     * other workers in a pool have stale rules, not the store they all
     * read from, and forcing every one of them to re-hit the database on
     * every request boundary would defeat the point of caching at all.
     */
    public function resetForNewRequest(): void
    {
        $this->rules = null;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $rules
     */
    private function matchExact(array $rules, string $path, ?string $locale): ?RedirectMatch
    {
        foreach ($rules[RedirectMatchType::Exact->value] ?? [] as $rule) {
            if ($rule['source'] === $path && $this->localeMatches($rule, $locale)) {
                return $this->toMatch($rule, RedirectMatchType::Exact, $rule['target']);
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $rules
     */
    private function matchPrefix(array $rules, string $path, ?string $locale): ?RedirectMatch
    {
        $best = null;
        $bestLength = -1;

        foreach ($rules[RedirectMatchType::Prefix->value] ?? [] as $rule) {
            $source = (string) $rule['source'];

            if (! $this->localeMatches($rule, $locale)) {
                continue;
            }

            if ($path !== $source && ! str_starts_with($path, rtrim($source, '/').'/')) {
                continue;
            }

            // Longest prefix wins, so /blog/2024 beats /blog.
            if (strlen($source) > $bestLength) {
                $best = $rule;
                $bestLength = strlen($source);
            }
        }

        if ($best === null) {
            return null;
        }

        $target = $best['target'];

        // Carry the remainder of the path across, so a moved section keeps its
        // deep links rather than dumping every visitor on one page.
        if (is_string($target) && $path !== $best['source']) {
            $remainder = substr($path, strlen((string) $best['source']));
            $target = rtrim($target, '/').$remainder;
        }

        return $this->toMatch($best, RedirectMatchType::Prefix, $target);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $rules
     */
    private function matchRegex(array $rules, string $path, ?string $locale): ?RedirectMatch
    {
        foreach ($rules[RedirectMatchType::Regex->value] ?? [] as $rule) {
            if (! $this->localeMatches($rule, $locale)) {
                continue;
            }

            $pattern = $this->guard->delimit((string) $rule['source']);
            $result = @preg_replace($pattern, (string) ($rule['target'] ?? ''), $path, 1, $count);

            if ($result === null || $count === 0) {
                continue;
            }

            return $this->toMatch($rule, RedirectMatchType::Regex, $result);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function localeMatches(array $rule, ?string $locale): bool
    {
        // A rule with no locale applies everywhere.
        return $rule['locale'] === null || $rule['locale'] === $locale;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function toMatch(array $rule, RedirectMatchType $type, ?string $target): RedirectMatch
    {
        $status = RedirectType::from((int) $rule['status']);

        return new RedirectMatch(
            ruleId: $rule['id'],
            status: $status,
            matchedBy: $type,
            target: $status->redirects() ? $target : null,
        );
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function rules(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $ttl = (int) $this->config->get('seo.redirects.cache_ttl', 3600);

        $load = function (): array {
            $grouped = [];

            foreach (Redirect::query()->where('is_active', true)->get() as $redirect) {
                $grouped[$redirect->source_type->value][] = [
                    'id' => $redirect->getKey(),
                    'source' => $redirect->source_path,
                    'target' => $redirect->target,
                    'status' => $redirect->status_code->value,
                    'locale' => $redirect->locale,
                ];
            }

            return $grouped;
        };

        /** @var array<string, list<array<string, mixed>>> $rules */
        $rules = $ttl > 0
            ? $this->cache->remember(self::CACHE_KEY, $ttl, $load)
            : $load();

        return $this->rules = $rules;
    }
}
