<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings;

use Duxbo\Seo\Contracts\ResetsBetweenRequests;
use Duxbo\Seo\Exceptions\UnknownSetting;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Config values that can be changed at runtime instead of only in
 * `config/seo.php` — a settings page can write here without needing a
 * deploy, the same reasoning behind `seo_redirects` existing at all instead
 * of `Route::redirect()` calls in a routes file.
 *
 * `applyToConfig()` is what actually makes an override take effect: it
 * pushes every stored value straight into Laravel's own config repository,
 * which is why nothing else in this package — `RobotsTxt`, `GlobalDefaultStage`,
 * the verification formatters — needed to change at all to support this.
 * They already read `config('seo.*')`; this only changes what that call
 * returns.
 *
 * The service provider calls `applyToConfig()` once at boot, which is the
 * whole story under ordinary PHP-FPM: config is re-read from scratch on
 * every request anyway. Under a long-running worker (Octane) "boot" happens
 * once for the worker's entire life, so `resetForNewRequest()` — called at
 * the start of every request there — runs the exact same push again, which
 * is how a setting saved through the API reaches an already-running worker
 * without waiting for it to restart.
 */
final class SettingsRepository implements ResetsBetweenRequests
{
    private const CACHE_KEY = 'duxbo.seo.settings.overrides';

    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
    ) {
    }

    public function enabled(): bool
    {
        return $this->config->get('seo.settings.enabled', false) === true;
    }

    /**
     * @return list<string>
     */
    public function allowedKeys(): array
    {
        /** @var list<string> $keys */
        $keys = $this->config->get('seo.settings.keys', []);

        return $keys;
    }

    /**
     * Every stored override, dot-key => decoded value. Empty when disabled
     * or when the table has not been migrated yet — a project that never
     * opted in must never have a request fail over a table that, by design,
     * does not need to exist for it.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $ttl = (int) $this->config->get('seo.settings.cache_ttl', 60);

        return $this->cache->remember(self::CACHE_KEY, $ttl, function (): array {
            try {
                return DB::table($this->table())
                    ->pluck('value', 'key')
                    ->map(static fn (?string $value): mixed => $value !== null ? json_decode($value, true) : null)
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * @throws UnknownSetting
     */
    public function set(string $key, mixed $value): void
    {
        $this->assertAllowed($key);

        DB::table($this->table())->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => now()],
        );

        $this->forgetCache();
        $this->applyToConfig();
    }

    /**
     * Removes the stored override so the *next* boot reads config/seo.php's
     * own value again. Does not retroactively restore it within the current
     * request: {@see set()} already overwrote the live config repository,
     * and nothing keeps the original value around to put back — the same
     * reason a fresh `php artisan config:cache` is needed after editing
     * config/seo.php by hand in a normal Laravel app.
     *
     * @throws UnknownSetting
     */
    public function forget(string $key): void
    {
        $this->assertAllowed($key);

        DB::table($this->table())->where('key', $key)->delete();

        $this->forgetCache();
        $this->applyToConfig();
    }

    /**
     * Pushes every stored override into the live config repository. Called
     * once at boot by the service provider — before that point, every
     * consumer in this package sees only what config/seo.php itself says,
     * exactly as if this feature did not exist.
     */
    public function applyToConfig(): void
    {
        foreach ($this->all() as $key => $value) {
            $this->config->set("seo.{$key}", $value);
        }
    }

    /**
     * Re-runs {@see applyToConfig()} — under a worker that stays booted
     * across requests, "once at boot" would otherwise mean "once, ever,
     * until the worker restarts," and a setting saved through the API
     * would never reach it. Still bounded by `seo.settings.cache_ttl`
     * underneath, so this does not turn into a database query on every
     * single request the way naively bypassing that cache here would.
     */
    public function resetForNewRequest(): void
    {
        $this->applyToConfig();
    }

    private function assertAllowed(string $key): void
    {
        if (! in_array($key, $this->allowedKeys(), true)) {
            throw UnknownSetting::named($key, $this->allowedKeys());
        }
    }

    private function forgetCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    private function table(): string
    {
        return (string) $this->config->get('seo.settings.table', 'seo_settings');
    }
}
