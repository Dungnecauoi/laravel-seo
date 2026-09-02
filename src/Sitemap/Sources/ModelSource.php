<?php

declare(strict_types=1);

namespace Duxbo\Seo\Sitemap\Sources;

use Closure;
use DateTimeInterface;
use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\SitemapSource;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SitemapUrl;
use Duxbo\Seo\Enums\ChangeFrequency;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * URLs from an Eloquent model.
 *
 * Opt-in one model at a time, and the model's own global scopes apply.
 * Registering every model carrying the trait automatically would push drafts,
 * private records and soft-deleted rows onto a public sitemap — a data leak
 * dressed up as a convenience.
 */
final class ModelSource implements SitemapSource
{
    /**
     * @param  class-string<Model&Seoable>  $model
     * @param  Closure(Builder<Model>): Builder<Model>|null  $scope
     */
    public function __construct(
        private readonly string $model,
        private readonly string $name,
        private readonly UrlGenerator $urls,
        private readonly LocaleResolver $locales,
        private readonly ?Closure $scope = null,
        private readonly ?ChangeFrequency $changeFrequency = null,
        private readonly ?float $priority = null,
        private readonly bool $enabled = true,
        private readonly int $chunk = 500,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return Generator<int, SitemapUrl>
     */
    public function urls(): Generator
    {
        $locales = $this->locales->supported();

        // lazyById, never get(): this is expected to run over tables with
        // millions of rows, and the generator is what keeps memory flat.
        foreach ($this->query()->lazyById($this->chunk) as $record) {
            /** @var Model&Seoable $record */
            $url = new SitemapUrl(
                loc: $record->seoUrl(),
                lastModified: $this->lastModifiedOf($record),
                changeFrequency: $this->changeFrequency,
                priority: $this->priority,
                alternates: $this->alternatesFor($record, $locales),
            );

            yield $url;
        }
    }

    public function lastModified(): ?DateTimeInterface
    {
        $model = new $this->model();
        $column = $model->getUpdatedAtColumn();

        if ($column === null) {
            return null;
        }

        // A cheap aggregate — never by walking urls(), which would load the
        // whole table just to date the index entry.
        $latest = $this->query()->max($model->qualifyColumn($column));

        return is_string($latest) ? new \DateTimeImmutable($latest) : null;
    }

    /**
     * @return Builder<Model>
     */
    private function query(): Builder
    {
        /** @var Builder<Model> $query */
        $query = $this->model::query();

        return $this->scope !== null ? ($this->scope)($query) : $query;
    }

    private function lastModifiedOf(Model $record): ?DateTimeInterface
    {
        $column = $record->getUpdatedAtColumn();

        if ($column === null) {
            return null;
        }

        $value = $record->getAttribute($column);

        return $value instanceof DateTimeInterface ? $value : null;
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    private function alternatesFor(Seoable $record, array $locales): array
    {
        if (count($locales) < 2) {
            return [];
        }

        $alternates = [];
        $canonical = $record->seoUrl();

        foreach ($locales as $locale) {
            $alternates[$locale] = $this->urls->alternate($canonical, $locale);
        }

        return $alternates;
    }
}
