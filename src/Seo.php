<?php

declare(strict_types=1);

namespace Duxbo\Seo;

use Duxbo\Seo\Ai\AiManager;
use Duxbo\Seo\Analysis\Analyzer;
use Duxbo\Seo\Contracts\AnalysisCheck;
use Duxbo\Seo\Contracts\HasBreadcrumbs;
use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Contracts\OutputFormatter;
use Duxbo\Seo\Contracts\SchemaProvider;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\TokenResolver;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\AnalysisReport;
use Duxbo\Seo\Data\DuplicateMatch;
use Duxbo\Seo\Data\SchemaGraph;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Events\SeoMetaSaved;
use Duxbo\Seo\Exceptions\CannotResolveUrl;
use Duxbo\Seo\Exceptions\UnknownFormatter;
use Duxbo\Seo\Resolution\Resolver;
use Duxbo\Seo\Resolution\SeoDataBuilder;
use Duxbo\Seo\Resolution\TokenExpander;
use Duxbo\Seo\Schema\GraphAssembler;
use Duxbo\Seo\Schema\SchemaValidator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\HtmlString;

/**
 * The package's entry point, reached through the `Seo` facade.
 */
final class Seo
{
    /** @var array<string, OutputFormatter> */
    private array $formatters = [];

    public function __construct(
        private readonly Resolver $resolver,
        private readonly TokenExpander $expander,
        private readonly MetadataRepository $repository,
        private readonly LocaleResolver $locales,
        private readonly GraphAssembler $assembler,
        private readonly SchemaValidator $validator,
        private readonly Analyzer $analyzer,
        private readonly AiManager $ai,
        private readonly UrlGenerator $urls,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * Resolved metadata for a record.
     */
    public function for(Seoable $model, ?string $locale = null): SeoData
    {
        return $this->context($model, $locale)->data;
    }

    /**
     * Resolved metadata for a URL that belongs to no model — a static page.
     */
    public function forUrl(string $url, ?string $locale = null): SeoData
    {
        return $this->resolve(
            SeoContext::forUrl($url, $locale ?? $this->locales->current()),
        )->data;
    }

    public function context(Seoable $model, ?string $locale = null): SeoContext
    {
        return $this->resolve(
            SeoContext::for($model, $locale ?? $this->locales->current()),
        );
    }

    /**
     * The single funnel every other entry point goes through.
     *
     * Nothing else may call the resolver directly. When context() did, model
     * breadcrumbs appeared through seoTags() and silently vanished through
     * Seo::schema() — the same shape of bug twice over.
     */
    public function resolve(SeoContext $context): SeoContext
    {
        return $this->resolver->resolve($this->enrich($context));
    }

    /**
     * Add what the model can contribute but the pipeline does not ask for.
     *
     * Done here rather than in the trait so every entry point behaves the same.
     * When this lived in the trait, breadcrumbs appeared through seoTags() and
     * silently vanished through Seo::schema().
     */
    private function enrich(SeoContext $context): SeoContext
    {
        $model = $context->model;

        if ($model instanceof HasBreadcrumbs && $context->get('breadcrumbs') === null) {
            $crumbs = $model->seoBreadcrumbs();

            if ($crumbs !== []) {
                $context = $context->put('breadcrumbs', $crumbs);
            }
        }

        return $context;
    }

    /**
     * Meta tags ready to drop into a Blade layout.
     */
    public function render(Seoable|SeoContext|null $subject = null, ?string $locale = null): HtmlString
    {
        $context = match (true) {
            $subject instanceof SeoContext => $subject,
            $subject instanceof Seoable => $this->context($subject, $locale),
            default => $this->resolve(
                SeoContext::forUrl(url()->current(), $locale ?? $this->locales->current()),
            ),
        };

        /** @var HtmlString $html */
        $html = $this->format('html', $context);

        return $html;
    }

    public function format(string $formatter, SeoContext $context): mixed
    {
        if (! isset($this->formatters[$formatter])) {
            throw UnknownFormatter::named($formatter, array_keys($this->formatters));
        }

        return $this->formatters[$formatter]->format($context);
    }

    /**
     * @param  SeoData|array<string, mixed>  $data  A dot-notated array is accepted.
     */
    public function save(Seoable $model, SeoData|array $data, ?string $locale = null): void
    {
        $this->repository->save(
            $model,
            is_array($data) ? SeoDataBuilder::fromDotted($data) : $data,
            $locale,
        );

        $this->events->dispatch(new SeoMetaSaved($model, $locale, $this->safeUrl($model, $locale)));
    }

    /**
     * The model's own URL — {@see Seoable::seoUrl()}, not
     * {@see UrlGenerator::forModel()} directly, since most models answer it
     * with their own override rather than the `seo.models.*.route` config
     * mapping that method depends on. Turned into the locale-specific
     * variant the same way hreflang alternates are, when a locale other than
     * the page's own was asked for.
     *
     * Never lets a save fail over a URL that cannot be resolved yet — that is
     * a presentation concern the event should surface as null, not something
     * {@see save()} itself should ever throw over.
     */
    private function safeUrl(Seoable $model, ?string $locale): ?string
    {
        try {
            $url = $model->seoUrl();

            return $locale !== null ? $this->urls->alternate($url, $locale) : $url;
        } catch (CannotResolveUrl) {
            return null;
        }
    }

    public function forget(Seoable $model, ?string $locale = null): void
    {
        $this->repository->delete($model, $locale);
    }

    /**
     * Other records whose stored title exactly matches — the live "this
     * title is already used" warning, for a panel or API caller to surface
     * to whoever is about to save it. Compares only what is stored, not
     * every record's resolved fallback chain; `php artisan seo:duplicates`
     * does the heavier, resolved comparison as an offline audit.
     *
     * @return list<DuplicateMatch>
     */
    public function duplicateTitles(Seoable $model, string $title, ?string $locale = null): array
    {
        return $this->repository->duplicateTitles($model, $title, $locale);
    }

    /**
     * @return list<DuplicateMatch>
     */
    public function duplicateDescriptions(Seoable $model, string $description, ?string $locale = null): array
    {
        return $this->repository->duplicateDescriptions($model, $description, $locale);
    }

    public function registerToken(TokenResolver $resolver): self
    {
        $this->expander->register($resolver);

        return $this;
    }

    public function removeToken(string $key): self
    {
        $this->expander->forget($key);

        return $this;
    }

    /**
     * The JSON-LD graph for a page, before rendering.
     */
    public function schema(Seoable|SeoContext $subject, ?string $locale = null): SchemaGraph
    {
        return $this->assembler->build(
            $subject instanceof SeoContext ? $subject : $this->context($subject, $locale),
        );
    }

    /**
     * Problems Google would silently drop the rich result over.
     *
     * @return list<string>
     */
    public function validateSchema(Seoable|SeoContext $subject, ?string $locale = null): array
    {
        return $this->validator->validate($this->schema($subject, $locale));
    }

    /**
     * @param  class-string<SchemaProvider>|SchemaProvider  $provider
     */
    public function registerSchema(string|SchemaProvider $provider): self
    {
        $this->assembler->register($provider);

        return $this;
    }

    /**
     * @param  class-string<SchemaProvider>  $provider
     */
    public function removeSchema(string $provider): self
    {
        $this->assembler->remove($provider);

        return $this;
    }

    public function graph(): GraphAssembler
    {
        return $this->assembler;
    }

    /**
     * Score a page's content.
     */
    public function analyze(
        string $content,
        ?string $keyword = null,
        ?string $title = null,
        ?string $description = null,
        ?string $url = null,
        ?string $locale = null,
    ): AnalysisReport {
        return $this->analyzer->analyze($content, $keyword, $title, $description, $url, $locale);
    }

    /**
     * Score a record, taking title, description and keyword from its resolved
     * metadata so the analysis matches what will actually be published.
     */
    public function analyzeModel(Seoable $model, string $content, ?string $locale = null): AnalysisReport
    {
        return $this->analyzer->analyzeContext($this->context($model, $locale), $content);
    }

    /**
     * @param  class-string<AnalysisCheck>|AnalysisCheck  $check
     */
    public function registerCheck(string|AnalysisCheck $check): self
    {
        $this->analyzer->register($check);

        return $this;
    }

    public function removeCheck(string $id): self
    {
        $this->analyzer->remove($id);

        return $this;
    }

    public function setCheckWeight(string $id, int $weight): self
    {
        $this->analyzer->setWeight($id, $weight);

        return $this;
    }

    public function analyzer(): Analyzer
    {
        return $this->analyzer;
    }

    /**
     * The language model manager — `Seo::ai()->driver('claude')`, or
     * `Seo::ai()->extend('my-llm', …)` to plug in your own.
     */
    public function ai(): AiManager
    {
        return $this->ai;
    }

    public function registerFormatter(OutputFormatter $formatter): self
    {
        $this->formatters[$formatter->name()] = $formatter;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function formatters(): array
    {
        return array_keys($this->formatters);
    }

    public function tokens(): TokenExpander
    {
        return $this->expander;
    }

    /**
     * The stage list, exposed so a project can reorder or replace it at runtime
     * rather than only through config.
     */
    public function pipeline(): Resolver
    {
        return $this->resolver;
    }

    public function repository(): MetadataRepository
    {
        return $this->repository;
    }
}
