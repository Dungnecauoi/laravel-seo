<?php

declare(strict_types=1);

namespace Duxbo\Seo\Sitemap\Sources;

use Closure;
use DateTimeInterface;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\SitemapSource;
use Duxbo\Seo\Data\SitemapNews;
use Duxbo\Seo\Data\SitemapUrl;
use Duxbo\Seo\Seo;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Google News' sitemap extension — a narrower, stricter cousin of the regular
 * sitemap most projects never need.
 *
 * Google News rejects an article older than 48 hours outright, so this only
 * ever lists what was just published — never the archive `ModelSource`
 * covers. That narrow window is also why full pipeline resolution per
 * record is affordable here in a way it deliberately is not in
 * `ModelSource`: a busy news site still only has a handful of articles
 * published in the last two days, not the millions a general sitemap has to
 * stream through.
 */
final class NewsSitemapSource implements SitemapSource
{
    /**
     * @param  class-string<Model&Seoable>  $model
     * @param  Closure(Builder<Model>): Builder<Model>|null  $scope
     */
    public function __construct(
        private readonly string $model,
        private readonly string $name,
        private readonly string $publicationName,
        private readonly string $publicationLanguage,
        private readonly string $dateColumn,
        private readonly Seo $seo,
        private readonly ?Closure $scope = null,
        private readonly int $maxAgeHours = 48,
        private readonly bool $enabled = true,
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
        $cutoff = Carbon::now()->subHours($this->maxAgeHours);

        foreach ($this->query()->where($this->dateColumn, '>=', $cutoff)->lazyById() as $record) {
            /** @var Model&Seoable $record */
            $publishedAt = $record->getAttribute($this->dateColumn);

            if (! $publishedAt instanceof DateTimeInterface) {
                continue;
            }

            // Resolved, not raw: the same title a visitor and Google Search
            // would actually see, including whatever the fallback chain
            // supplies for a record with no custom SEO title of its own.
            $data = $this->seo->for($record);
            $title = $data->title ?? (string) $record->getAttribute('name');

            if ($data->hasRobotsDirective('noindex')) {
                continue;
            }

            yield new SitemapUrl(
                loc: $record->seoUrl(),
                news: new SitemapNews(
                    publicationName: $this->publicationName,
                    publicationLanguage: $this->publicationLanguage,
                    publicationDate: $publishedAt,
                    title: $title,
                ),
            );
        }
    }

    public function lastModified(): ?DateTimeInterface
    {
        $latest = $this->query()->max($this->dateColumn);

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
}
