<?php

declare(strict_types=1);

namespace Duxbo\Seo;

use Duxbo\Seo\Support\Compat;
use Illuminate\Support\ServiceProvider;

/**
 * Written by hand rather than on top of a package-scaffolding library.
 *
 * A scaffolding dependency would be exactly the kind of third-party coupling
 * this package exists without: if it is abandoned or breaks on a new Laravel
 * major, every release here stalls behind it.
 */
final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'seo');
    }

    public function boot(): void
    {
        // Fail here, with an actionable message, rather than somewhere obscure
        // deep in a request three weeks from now.
        Compat::assertSupported();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => config_path('seo.php'),
            ], 'seo-config');
        }
    }

    private function configPath(): string
    {
        return __DIR__.'/../config/seo.php';
    }
}
