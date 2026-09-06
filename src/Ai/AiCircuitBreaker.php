<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai;

use Duxbo\Seo\Contracts\ResetsBetweenRequests;
use Duxbo\Seo\Exceptions\AiCircuitOpen;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Stops sending requests to a driver that keeps failing, instead of retrying
 * it once per record in a loop that has no idea the whole provider is down.
 *
 * The shared `Cache` store is where the real failure count lives — under a
 * long-running worker, this instance's own `$state` is only a same-process
 * memo of it, dropped by {@see resetForNewRequest()} for the same reason
 * {@see \Duxbo\Seo\Redirects\CachedRedirectMatcher} drops its own: a
 * different worker's process can flip the circuit, and this one must not
 * keep answering from a stale local copy.
 */
final class AiCircuitBreaker implements ResetsBetweenRequests
{
    private const CACHE_PREFIX = 'duxbo.seo.ai_circuit.';

    /**
     * @var array<string, array{failures: int, openUntil: int|null}>
     */
    private array $state = [];

    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
    ) {
    }

    public function resetForNewRequest(): void
    {
        $this->state = [];
    }

    /**
     * @throws AiCircuitOpen
     */
    public function assertClosed(string $driver): void
    {
        if (! $this->enabled()) {
            return;
        }

        $openUntil = $this->state($driver)['openUntil'];

        if ($openUntil !== null && $openUntil > time()) {
            throw AiCircuitOpen::forDriver($driver, $openUntil);
        }
    }

    public function recordSuccess(string $driver): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->state[$driver] = ['failures' => 0, 'openUntil' => null];
        $this->cache->forget($this->key($driver));
    }

    public function recordFailure(string $driver): void
    {
        if (! $this->enabled()) {
            return;
        }

        $failures = $this->state($driver)['failures'] + 1;
        $threshold = max(1, (int) $this->config->get('seo.ai.circuit_breaker.threshold', 5));
        $cooldown = max(1, (int) $this->config->get('seo.ai.circuit_breaker.cooldown_seconds', 60));

        $next = [
            'failures' => $failures,
            'openUntil' => $failures >= $threshold ? time() + $cooldown : null,
        ];

        $this->state[$driver] = $next;
        $this->cache->put($this->key($driver), $next, $cooldown + 60);
    }

    private function enabled(): bool
    {
        return $this->config->get('seo.ai.circuit_breaker.enabled', true) === true;
    }

    /**
     * @return array{failures: int, openUntil: int|null}
     */
    private function state(string $driver): array
    {
        if (isset($this->state[$driver])) {
            return $this->state[$driver];
        }

        /** @var array{failures: int, openUntil: int|null}|null $stored */
        $stored = $this->cache->get($this->key($driver));

        return $this->state[$driver] = $stored ?? ['failures' => 0, 'openUntil' => null];
    }

    private function key(string $driver): string
    {
        return self::CACHE_PREFIX.$driver;
    }
}
