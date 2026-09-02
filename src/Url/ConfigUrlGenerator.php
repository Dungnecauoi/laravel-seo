<?php

declare(strict_types=1);

namespace Duxbo\Seo\Url;

use Closure;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\UrlGenerator as UrlGeneratorContract;
use Duxbo\Seo\Exceptions\CannotResolveUrl;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator as LaravelUrls;
use Illuminate\Routing\Router;

/**
 * Resolves a record's public URL from a route declared in config.
 *
 * A URL genuinely cannot be guessed, so when nothing is declared this raises an
 * exception naming the two ways to declare one rather than inventing a plausible
 * wrong answer that only shows up as a bad canonical tag in production.
 */
final class ConfigUrlGenerator implements UrlGeneratorContract
{
    public function __construct(
        private readonly Config $config,
        private readonly LaravelUrls $urls,
        private readonly Router $router,
    ) {
    }

    public function forModel(Seoable $model, ?string $locale = null): string
    {
        $class = $model::class;

        /** @var array<string, mixed>|Closure|string|null $mapping */
        $mapping = $this->config->get("seo.models.{$class}.route");

        if ($mapping instanceof Closure) {
            return $this->absolute((string) $mapping($model, $locale));
        }

        if (is_string($mapping)) {
            $mapping = ['name' => $mapping];
        }

        if (! is_array($mapping) || ! isset($mapping['name']) || ! is_string($mapping['name'])) {
            throw CannotResolveUrl::forModel($class);
        }

        $name = $mapping['name'];

        if ($this->router->getRoutes()->getByName($name) === null) {
            throw CannotResolveUrl::missingRoute($class, $name);
        }

        $parameter = is_string($mapping['parameter'] ?? null) ? $mapping['parameter'] : null;
        $binding = is_string($mapping['binding'] ?? null) ? $mapping['binding'] : null;

        $value = $binding !== null
            ? ($model->seoAttributes()[$binding] ?? $model->seoKey())
            : $model->seoKey();

        $parameters = $parameter !== null ? [$parameter => $value] : [$value];

        if ($locale !== null && ($mapping['locale_parameter'] ?? null) !== null) {
            /** @var string $localeParameter */
            $localeParameter = $mapping['locale_parameter'];
            $parameters[$localeParameter] = $locale;
        }

        return $this->urls->route($name, $parameters);
    }

    public function alternate(string $url, string $locale): string
    {
        /** @var Closure|null $resolver */
        $resolver = $this->config->get('seo.locales.alternate_url');

        if ($resolver instanceof Closure) {
            return $this->absolute((string) $resolver($url, $locale));
        }

        // Default convention: a locale segment directly after the host.
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        foreach ($this->supportedLocales() as $known) {
            if (str_starts_with($path, "/{$known}/") || $path === "/{$known}") {
                $path = substr($path, strlen($known) + 1);

                break;
            }
        }

        $path = '/'.$locale.'/'.ltrim($path, '/');

        return $scheme.'://'.$parts['host'].rtrim($path, '/').$query;
    }

    public function absolute(string $url): string
    {
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return $this->urls->to($url);
    }

    public function home(): string
    {
        return rtrim($this->urls->to('/'), '/').'/';
    }

    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        /** @var list<string> $supported */
        $supported = $this->config->get('seo.locales.supported', []);

        return $supported;
    }
}
