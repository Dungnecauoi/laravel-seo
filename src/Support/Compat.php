<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

use Closure;
use Duxbo\Seo\Exceptions\UnsupportedLaravelVersionException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Every difference between Laravel 9 and 13 lives here.
 *
 * The rule this class exists to enforce: no `version_compare` anywhere else in
 * the package. Scattering version checks through feature code is how a package
 * spanning five majors rots — each check is invisible until it breaks, and no
 * one can tell which framework versions are still exercised.
 */
final class Compat
{
    public const MIN_LARAVEL = 9;

    public const MAX_LARAVEL = 13;

    private static ?int $major = null;

    private function __construct()
    {
        // Static-only.
    }

    /**
     * Major version of the running framework, e.g. 11.
     */
    public static function laravel(): int
    {
        return self::$major ??= (int) explode('.', self::version())[0];
    }

    public static function version(): string
    {
        /** @var Application $app */
        $app = app();

        return $app->version();
    }

    public static function atLeast(int $major): bool
    {
        return self::laravel() >= $major;
    }

    /**
     * Throw unless the host framework is inside the supported range.
     */
    public static function assertSupported(): void
    {
        $major = self::laravel();

        if ($major < self::MIN_LARAVEL || $major > self::MAX_LARAVEL) {
            throw UnsupportedLaravelVersionException::make(
                self::version(),
                self::MIN_LARAVEL,
                self::MAX_LARAVEL,
            );
        }
    }

    /**
     * `laravel/prompts` ships with the framework from Laravel 11.
     *
     * Console commands use it when present and fall back to Symfony's question
     * helper otherwise, so the package never has to require it.
     */
    public static function supportsPrompts(): bool
    {
        return self::atLeast(11) && function_exists('Laravel\Prompts\select');
    }

    /**
     * Publish migrations, using the framework helper where it exists.
     *
     * `publishesMigrations()` arrived in Laravel 11 and stamps each file with a
     * fresh timestamp on publish. Before that the provider has to do the
     * stamping itself, or repeated publishes collide.
     *
     * @param  string  $from  Directory holding the package's migration stubs.
     */
    public static function publishMigrations(ServiceProvider $provider, string $from, string $group = 'seo-migrations'): void
    {
        if (self::atLeast(11) && method_exists($provider, 'publishesMigrations')) {
            $provider->publishesMigrations([$from => database_path('migrations')], $group);

            return;
        }

        $paths = [];
        $offset = 0;

        foreach (self::migrationStubs($from) as $stub) {
            $name = self::migrationName($stub);
            $timestamp = date('Y_m_d_His', time() + $offset++);

            $paths[$stub] = database_path("migrations/{$timestamp}_{$name}.php");
        }

        if ($paths !== []) {
            $provider->publishes($paths, $group);
        }
    }

    /**
     * Register a scheduled task from inside a package.
     *
     * The `Schedule` facade only exists from Laravel 11. Resolving the schedule
     * inside a `booted` callback works on every supported version and, more
     * importantly, defers resolution until the console kernel is ready.
     */
    public static function schedule(Closure $callback): void
    {
        app()->booted(static function () use ($callback): void {
            if (! app()->runningInConsole()) {
                return;
            }

            $callback(app(Schedule::class));
        });
    }

    /**
     * Reset memoised state. Test seam only.
     *
     * @internal
     */
    public static function flush(): void
    {
        self::$major = null;
    }

    /**
     * @return list<string>
     */
    private static function migrationStubs(string $from): array
    {
        $files = glob(rtrim($from, '/').'/*.php');

        return $files === false ? [] : array_values($files);
    }

    /**
     * Strip any leading timestamp from a stub filename.
     */
    private static function migrationName(string $path): string
    {
        $base = basename($path, '.php');

        return (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $base);
    }
}
