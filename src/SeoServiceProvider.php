<?php

declare(strict_types=1);

namespace Duxbo\Seo;

use Closure;
use Duxbo\Seo\Analysis\Analyzer;
use Duxbo\Seo\Analysis\DomContentExtractor;
use Duxbo\Seo\Console\AuditCommand;
use Duxbo\Seo\Console\DuplicatesCommand;
use Duxbo\Seo\Console\HreflangAuditCommand;
use Duxbo\Seo\Console\IndexNowCommand;
use Duxbo\Seo\Console\PruneNotFoundCommand;
use Duxbo\Seo\Console\SitemapCommand;
use Duxbo\Seo\Contracts\ContentExtractor;
use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Contracts\RedirectMatcher;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Formatters\ArrayFormatter;
use Duxbo\Seo\Formatters\HeadFormatter;
use Duxbo\Seo\Formatters\HtmlFormatter;
use Duxbo\Seo\Formatters\JsonLdFormatter;
use Duxbo\Seo\Formatters\NextMetadataFormatter;
use Duxbo\Seo\Http\Middleware\HandleNotFound;
use Duxbo\Seo\Locale\AlternateLocaleResolver;
use Duxbo\Seo\Locale\AppLocaleResolver;
use Duxbo\Seo\Redirects\CachedRedirectMatcher;
use Duxbo\Seo\Resolution\Resolver;
use Duxbo\Seo\Resolution\TokenExpander;
use Duxbo\Seo\Resolution\Tokens\AttributeToken;
use Duxbo\Seo\Resolution\Tokens\ConfigToken;
use Duxbo\Seo\Resolution\Tokens\DateToken;
use Duxbo\Seo\Resolution\Tokens\FieldToken;
use Duxbo\Seo\Resolution\Tokens\NowToken;
use Duxbo\Seo\Schema\GraphAssembler;
use Duxbo\Seo\Schema\SchemaNormalizer;
use Duxbo\Seo\Schema\SchemaValidator;
use Duxbo\Seo\Support\SiteIndexability;
use Duxbo\Seo\Sitemap\SitemapGenerator;
use Duxbo\Seo\Sitemap\Sources\ModelSource;
use Duxbo\Seo\Sitemap\Sources\RouteSource;
use Duxbo\Seo\Storage\EloquentMetadataRepository;
use Duxbo\Seo\Support\Compat;
use Duxbo\Seo\Url\ConfigUrlGenerator;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
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

        $this->app->singleton(Storage\SeoDataMapper::class);
        $this->app->singleton(SchemaNormalizer::class);
        $this->app->singleton(SchemaValidator::class);
        $this->app->singleton(Redirects\RedirectGuard::class);
        $this->app->singleton(NotFound\NotFoundLogger::class);
        $this->app->singleton(Robots\RobotsTxt::class);
        $this->app->singleton(Ai\AiBudget::class);
        $this->app->singleton(Ai\PromptLibrary::class);
        $this->app->singleton(Ai\AiManager::class);
        $this->app->singleton(SiteIndexability::class);
        $this->app->singleton(AlternateLocaleResolver::class);
        $this->app->singleton(\Duxbo\Seo\Support\SameOriginUrls::class);

        // Contracts, not concretes: every one of these is meant to be swapped.
        $this->app->singleton(LocaleResolver::class, AppLocaleResolver::class);
        $this->app->singleton(UrlGenerator::class, ConfigUrlGenerator::class);
        $this->app->singleton(MetadataRepository::class, EloquentMetadataRepository::class);
        $this->app->singleton(ContentExtractor::class, DomContentExtractor::class);
        $this->app->singleton(RedirectMatcher::class, CachedRedirectMatcher::class);

        $this->registerTokens();
        $this->registerResolver();
        $this->registerSchema();
        $this->registerSitemap();
        $this->registerAnalyzer();
        $this->registerManager();
    }

    public function boot(): void
    {
        // Fail here, with an actionable message, rather than somewhere obscure
        // deep in a request three weeks from now.
        Compat::assertSupported();

        $this->registerBladeDirective();
        $this->registerGate();
        $this->registerRoutes();
        $this->registerMiddleware();

        // Check messages are translation keys, not sentences, so a panel can
        // render either language without the check knowing which.
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'seo');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'seo');
        $this->registerPanelViewComposer();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => config_path('seo.php'),
            ], 'seo-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/seo'),
            ], 'seo-lang');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/seo'),
            ], 'seo-views');

            $this->publishMigrations();

            $this->commands([
                SitemapCommand::class,
                PruneNotFoundCommand::class,
                DuplicatesCommand::class,
                HreflangAuditCommand::class,
                IndexNowCommand::class,
                AuditCommand::class,
            ]);
        }
    }

    private function registerTokens(): void
    {
        $this->app->singleton(TokenExpander::class, function ($app): TokenExpander {
            $config = $app->make(Config::class);
            $expander = new TokenExpander($config);

            foreach ([
                new ConfigToken('sitename', 'seo.site_name', $config),
                new ConfigToken('sep', 'seo.separator', $config),

                new AttributeToken('title', ['title', 'name', 'heading', 'label']),
                new AttributeToken('excerpt', ['excerpt', 'summary', 'description']),
                new AttributeToken('description', ['description', 'excerpt', 'summary']),
                new AttributeToken('author', ['author_name', 'author']),
                new AttributeToken('category', ['category', 'categories', 'category_name']),
                new AttributeToken('tag', ['tag', 'tags']),
                new AttributeToken('parent_title', ['parent_title']),

                new DateToken('date', ['published_at', 'created_at'], $config),
                new DateToken('modified', ['updated_at'], $config),

                new NowToken('currentyear', 'Y'),
                new NowToken('currentdate', 'd/m/Y'),

                new FieldToken(),
            ] as $token) {
                $expander->register($token);
            }

            return $expander;
        });
    }

    private function registerResolver(): void
    {
        $this->app->singleton(Resolver::class, function ($app): Resolver {
            /** @var list<class-string<Contracts\ResolverStage>> $stages */
            $stages = $app->make(Config::class)->get('seo.pipeline', []);

            return new Resolver($app, $stages);
        });
    }

    private function registerSchema(): void
    {
        $this->app->singleton(GraphAssembler::class, function ($app): GraphAssembler {
            $config = $app->make(Config::class);
            $assembler = new GraphAssembler($app, $app->make(SchemaNormalizer::class));

            if ($config->get('seo.schema.enabled', true) !== true) {
                return $assembler;
            }

            /** @var list<class-string<Contracts\SchemaProvider>> $providers */
            $providers = $config->get('seo.schema.providers', []);

            foreach ($providers as $provider) {
                $assembler->register($provider);
            }

            return $assembler;
        });
    }

    private function registerSitemap(): void
    {
        $this->app->singleton(SitemapGenerator::class, function ($app): SitemapGenerator {
            $config = $app->make(Config::class);

            $generator = new SitemapGenerator(
                $config,
                $app->make(UrlGenerator::class),
                $app->make('events'),
                $app->make(SiteIndexability::class),
            );

            /** @var list<array<string, mixed>> $sources */
            $sources = $config->get('seo.sitemap.sources', []);

            foreach ($sources as $definition) {
                $source = $this->makeSitemapSource($definition, $app);

                if ($source !== null) {
                    $generator->register($source);
                }
            }

            return $generator;
        });
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function makeSitemapSource(array $definition, \Illuminate\Contracts\Container\Container $app): ?Contracts\SitemapSource
    {
        if (isset($definition['pages']) && is_array($definition['pages'])) {
            /** @var list<string|array<string, mixed>> $pages */
            $pages = $definition['pages'];

            return new RouteSource(
                entries: $pages,
                urls: $app->make(UrlGenerator::class),
                name: is_string($definition['name'] ?? null) ? $definition['name'] : 'pages',
            );
        }

        $model = $definition['model'] ?? null;

        if (! is_string($model) || ! class_exists($model)) {
            return null;
        }

        // A model source carrying a 'news' block builds a NewsSitemapSource
        // instead of a plain ModelSource — a separate, stricter sitemap
        // Google News rejects an article from after 48 hours, so it is
        // opt-in per source rather than something every model source gets.
        $news = $definition['news'] ?? null;

        if (is_array($news)) {
            $dateColumn = $news['date_column'] ?? null;
            $publicationName = $news['publication_name'] ?? null;
            $publicationLanguage = $news['publication_language'] ?? null;

            if (! is_string($dateColumn) || ! is_string($publicationName) || ! is_string($publicationLanguage)) {
                return null;
            }

            return new Sitemap\Sources\NewsSitemapSource(
                model: $model,
                name: is_string($definition['name'] ?? null) ? $definition['name'] : 'news',
                publicationName: $publicationName,
                publicationLanguage: $publicationLanguage,
                dateColumn: $dateColumn,
                seo: $app->make(Seo::class),
                scope: ($definition['scope'] ?? null) instanceof Closure ? $definition['scope'] : null,
                maxAgeHours: isset($news['max_age_hours']) ? (int) $news['max_age_hours'] : 48,
                enabled: ($definition['enabled'] ?? true) === true,
            );
        }

        $frequency = $definition['changefreq'] ?? null;

        return new ModelSource(
            model: $model,
            name: is_string($definition['name'] ?? null)
                ? $definition['name']
                : strtolower(class_basename($model)).'s',
            urls: $app->make(UrlGenerator::class),
            alternateLocales: $app->make(AlternateLocaleResolver::class),
            repository: $app->make(MetadataRepository::class),
            scope: ($definition['scope'] ?? null) instanceof Closure ? $definition['scope'] : null,
            changeFrequency: is_string($frequency) ? Enums\ChangeFrequency::tryFrom($frequency) : null,
            priority: isset($definition['priority']) ? (float) $definition['priority'] : null,
            enabled: ($definition['enabled'] ?? true) === true,
        );
    }

    private function registerAnalyzer(): void
    {
        $this->app->singleton(Analyzer::class, function ($app): Analyzer {
            $config = $app->make(Config::class);

            $analyzer = new Analyzer(
                $app,
                $app->make(ContentExtractor::class),
                $config,
                $app->make('events'),
            );

            /** @var list<class-string<Contracts\AnalysisCheck>> $checks */
            $checks = $config->get('seo.analysis.checks', []);

            foreach ($checks as $check) {
                $analyzer->register($check);
            }

            /** @var array<string, int> $weights */
            $weights = $config->get('seo.analysis.weights', []);

            foreach ($weights as $id => $weight) {
                $analyzer->setWeight($id, (int) $weight);
            }

            return $analyzer;
        });
    }

    private function registerManager(): void
    {
        $this->app->singleton(Seo::class, function ($app): Seo {
            $seo = new Seo(
                $app->make(Resolver::class),
                $app->make(TokenExpander::class),
                $app->make(MetadataRepository::class),
                $app->make(LocaleResolver::class),
                $app->make(GraphAssembler::class),
                $app->make(SchemaValidator::class),
                $app->make(Analyzer::class),
                $app->make(Ai\AiManager::class),
                $app->make(UrlGenerator::class),
                $app->make(Dispatcher::class),
            );

            $head = $app->make(HeadFormatter::class);

            foreach ([
                $app->make(HtmlFormatter::class),
                $app->make(ArrayFormatter::class),
                $app->make(JsonLdFormatter::class),
                $app->make(NextMetadataFormatter::class),
                // Nuxt 3 is built on Unhead, so one formatter serves both — it
                // is registered twice so a front end asks for its own word.
                $head->withName('nuxt'),
                $head->withName('vue'),
            ] as $formatter) {
                $seo->registerFormatter($formatter);
            }

            return $seo;
        });
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/seo.php');

        $config = $this->app->make(Config::class);

        if ($config->get('seo.api.enabled', false) === true) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        if ($config->get('seo.panel.enabled', false) === true) {
            $this->loadRoutesFrom(__DIR__.'/../routes/panel.php');
        }
    }

    /**
     * Deny by default.
     *
     * An SEO panel can rewrite every title on a site and redirect any URL, so
     * forgetting to define this Gate must lock the door rather than open it.
     * The application overrides it with its own definition.
     */
    private function registerGate(): void
    {
        if (! Gate::has('viewSeoPanel')) {
            Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => false);
        }
    }

    /**
     * Pushed onto the global stack so a 404 from any route reaches it.
     *
     * Registered through the kernel rather than the application's own middleware
     * file, which moved in Laravel 11 — this call works on every supported
     * version.
     */
    private function registerMiddleware(): void
    {
        $enabled = $this->app->make(Config::class);

        if ($enabled->get('seo.redirects.enabled', true) !== true
            && $enabled->get('seo.not_found.enabled', true) !== true) {
            return;
        }

        if (! $this->app->bound(Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(HandleNotFound::class);
        }
    }

    /**
     * `@seo` renders the meta tags for the current page.
     */
    /**
     * The 404 count badge in the nav needs a value on every panel page, not
     * only the 404 monitor's own — a View composer is the one place that
     * covers all of them without every controller remembering to pass it.
     */
    private function registerPanelViewComposer(): void
    {
        \Illuminate\Support\Facades\View::composer('seo::panel.layout', static function ($view): void {
            if (array_key_exists('notFoundCount', $view->getData())) {
                return;
            }

            try {
                $count = \Illuminate\Support\Facades\DB::table((string) config('seo.not_found.table', 'seo_not_found'))->count();
            } catch (\Throwable) {
                // Migrations may not have run yet; a broken nav badge is not
                // worth a fatal error over.
                $count = 0;
            }

            $view->with('notFoundCount', $count);
        });
    }

    private function registerBladeDirective(): void
    {
        Blade::directive('seo', static function (string $expression): string {
            return "<?php echo app(\\Duxbo\\Seo\\Seo::class)->render({$expression}); ?>";
        });
    }

    /**
     * `publishesMigrations()` is protected and only exists from Laravel 11, so
     * the version knowledge stays in Compat while the call stays here.
     */
    private function publishMigrations(): void
    {
        $from = __DIR__.'/../database/migrations';

        if (Compat::stampsMigrationsOnPublish()) {
            $this->publishesMigrations([$from => database_path('migrations')], 'seo-migrations');

            return;
        }

        $paths = Compat::stampedMigrationMap($from);

        if ($paths !== []) {
            $this->publishes($paths, 'seo-migrations');
        }
    }

    private function configPath(): string
    {
        return __DIR__.'/../config/seo.php';
    }
}
