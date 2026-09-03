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
use Duxbo\Seo\Data\AnalysisReport;
use Duxbo\Seo\Data\SchemaGraph;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Exceptions\UnknownFormatter;
use Duxbo\Seo\Resolution\Resolver;
use Duxbo\Seo\Resolution\SeoDataBuilder;
use Duxbo\Seo\Resolution\TokenExpander;
use Duxbo\Seo\Schema\GraphAssembler;
use Duxbo\Seo\Schema\SchemaValidator;
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
    }

    public function forget(Seoable $model, ?string $locale = null): void
    {
        $this->repository->delete($model, $locale);
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
