<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use DateTimeInterface;

/**
 * One `<news:news>` entry, per Google News' sitemap extension.
 *
 * Google News rejects an article older than 48 hours outright — a news
 * sitemap is a rolling window onto what was *just* published, not an
 * archive. `NewsSitemapSource` filters by this before a `SitemapNews` is
 * ever built, so its presence here always means the article was fresh
 * enough at build time.
 */
final class SitemapNews
{
    public function __construct(
        public readonly string $publicationName,
        public readonly string $publicationLanguage,
        public readonly DateTimeInterface $publicationDate,
        public readonly string $title,
        public readonly ?string $genres = null,
        public readonly ?string $keywords = null,
    ) {
    }
}
