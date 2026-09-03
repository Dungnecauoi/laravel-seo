<?php

declare(strict_types=1);

namespace Duxbo\Seo\Sitemap\Sources;

use Closure;
use DateTimeInterface;
use Duxbo\Seo\Contracts\HasSitemapVideo;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\SitemapSource;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SitemapUrl;
use Duxbo\Seo\Enums\ChangeFrequency;
use Duxbo\Seo\Locale\AlternateLocaleResolver;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
        private readonly AlternateLocaleResolver $alternateLocales,
        private readonly MetadataRepository $repository,
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
        // lazyById, never get(): this is expected to run over tables with
        // millions of rows, and the generator is what keeps memory flat.
        // chunk() on top batches the metadata lookup below — findMany() once
        // per chunk rather than once per row, the same trade this package
        // already makes for withSeo() on an index page. Each chunk is itself
        // still a LazyCollection, so collect() materialises only that one
        // chunk (bounded by $this->chunk) rather than the whole table —
        // findMany() needs an eager Collection to group by morph type.
        foreach ($this->query()->lazyById($this->chunk)->chunk($this->chunk) as $lazyBatch) {
            /** @var Collection<int, Model&Seoable> $batch */
            $batch = $lazyBatch->collect();
            $stored = $this->repository->findMany($batch);

            foreach ($batch as $record) {
                $key = $record->seoType().':'.$record->seoKey();

                // A record explicitly marked noindex must not appear in the
                // sitemap — telling a crawler "please index this" here and
                // "don't" in its own robots meta is exactly the contradiction
                // Search Console flags as "Submitted URL marked noindex".
                //
                // Only the *stored* value is checked, not the full resolution
                // pipeline: resolving every row through several stages here
                // would mean the streaming design this class exists for no
                // longer holds for a large table. A noindex applied only via
                // a model-wide template or default, never entered as a
                // specific record's own metadata, is not caught by this — if
                // a whole model should never appear in the sitemap, the fix
                // is not registering it as a source at all.
                if ($stored->get($key)?->hasRobotsDirective('noindex') === true) {
                    continue;
                }

                yield new SitemapUrl(
                    loc: $record->seoUrl(),
                    lastModified: $this->lastModifiedOf($record),
                    changeFrequency: $this->changeFrequency,
                    priority: $this->priority,
                    alternates: $this->alternatesFor($record),
                    videos: $record instanceof HasSitemapVideo ? $record->seoSitemapVideos() : [],
                );
            }
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
     * @return array<string, string>
     */
    private function alternatesFor(Seoable $record): array
    {
        $locales = $this->alternateLocales->resolve($record);

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
