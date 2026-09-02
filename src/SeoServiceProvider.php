<?php

declare(strict_types=1);

namespace Duxbo\Seo;

use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Formatters\ArrayFormatter;
use Duxbo\Seo\Formatters\HtmlFormatter;
use Duxbo\Seo\Formatters\JsonLdFormatter;
use Duxbo\Seo\Locale\AppLocaleResolver;
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
use Duxbo\Seo\Storage\EloquentMetadataRepository;
use Duxbo\Seo\Storage\SeoDataMapper;
use Duxbo\Seo\Support\Compat;
use Duxbo\Seo\Url\ConfigUrlGenerator;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Blade;
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

        $this->app->singleton(SeoDataMapper::class);

        // Contracts, not concretes: every one of these is meant to be swapped.
        $this->app->singleton(LocaleResolver::class, AppLocaleResolver::class);
        $this->app->singleton(UrlGenerator::class, ConfigUrlGenerator::class);
        $this->app->singleton(MetadataRepository::class, EloquentMetadataRepository::class);

        $this->app->singleton(TokenExpander::class, function ($app): TokenExpander {
            $expander = new TokenExpander($app->make(Config::class));

            foreach ($this->defaultTokens($app->make(Config::class)) as $token) {
                $expander->register($token);
            }

            return $expander;
        });

        $this->app->singleton(Resolver::class, function ($app): Resolver {
            /** @var list<class-string<\Duxbo\Seo\Contracts\ResolverStage>> $stages */
            $stages = $app->make(Config::class)->get('seo.pipeline', []);

            return new Resolver($app, $stages);
        });

        $this->app->singleton(GraphAssembler::class, function ($app): GraphAssembler {
            $assembler = new GraphAssembler($app, $app->make(SchemaNormalizer::class));

            if ($app->make(Config::class)->get('seo.schema.enabled', true) !== true) {
                return $assembler;
            }

            /** @var list<class-string<\Duxbo\Seo\Contracts\SchemaProvider>> $providers */
            $providers = $app->make(Config::class)->get('seo.schema.providers', []);

            foreach ($providers as $provider) {
                $assembler->register($provider);
            }

            return $assembler;
        });

        $this->app->singleton(Seo::class, function ($app): Seo {
            $seo = new Seo(
                $app->make(Resolver::class),
                $app->make(TokenExpander::class),
                $app->make(MetadataRepository::class),
                $app->make(LocaleResolver::class),
                $app->make(GraphAssembler::class),
                $app->make(SchemaValidator::class),
            );

            $seo->registerFormatter($app->make(HtmlFormatter::class));
            $seo->registerFormatter($app->make(ArrayFormatter::class));
            $seo->registerFormatter($app->make(JsonLdFormatter::class));

            return $seo;
        });
    }

    public function boot(): void
    {
        // Fail here, with an actionable message, rather than somewhere obscure
        // deep in a request three weeks from now.
        Compat::assertSupported();

        $this->registerBladeDirective();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => config_path('seo.php'),
            ], 'seo-config');

            $this->publishMigrations();
        }
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

    /**
     * `@seo` renders the meta tags for the current page.
     */
    private function registerBladeDirective(): void
    {
        Blade::directive('seo', static function (string $expression): string {
            return "<?php echo app(\\Duxbo\\Seo\\Seo::class)->render({$expression}); ?>";
        });
    }

    /**
     * @return list<\Duxbo\Seo\Contracts\TokenResolver>
     */
    private function defaultTokens(Config $config): array
    {
        return [
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
        ];
    }

    private function configPath(): string
    {
        return __DIR__.'/../config/seo.php';
    }
}
