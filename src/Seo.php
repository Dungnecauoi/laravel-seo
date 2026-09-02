<?php

declare(strict_types=1);

namespace Duxbo\Seo;

use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Contracts\OutputFormatter;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\TokenResolver;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Exceptions\UnknownFormatter;
use Duxbo\Seo\Resolution\Resolver;
use Duxbo\Seo\Resolution\SeoDataBuilder;
use Duxbo\Seo\Resolution\TokenExpander;
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
        return $this->resolver
            ->resolve(SeoContext::forUrl($url, $locale ?? $this->locales->current()))
            ->data;
    }

    public function context(Seoable $model, ?string $locale = null): SeoContext
    {
        return $this->resolver->resolve(
            SeoContext::for($model, $locale ?? $this->locales->current()),
        );
    }

    public function resolve(SeoContext $context): SeoContext
    {
        return $this->resolver->resolve($context);
    }

    /**
     * Meta tags ready to drop into a Blade layout.
     */
    public function render(Seoable|SeoContext|null $subject = null, ?string $locale = null): HtmlString
    {
        $context = match (true) {
            $subject instanceof SeoContext => $subject,
            $subject instanceof Seoable => $this->context($subject, $locale),
            default => $this->resolver->resolve(
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
