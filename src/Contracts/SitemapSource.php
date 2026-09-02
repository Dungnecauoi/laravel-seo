<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use DateTimeInterface;
use Generator;

/**
 * A stream of URLs for the sitemap.
 *
 * Sources are opt-in one at a time and must respect their model's scopes.
 * Auto-registering every model carrying the trait would push drafts, private
 * records and soft-deleted rows onto a public sitemap.
 */
interface SitemapSource
{
    /**
     * Slug for the generated file: `posts` becomes `sitemap-posts.xml`.
     */
    public function name(): string;

    /**
     * URLs, yielded lazily.
     *
     * A Generator rather than an array, and not negotiable: model sources are
     * expected to run `lazyById()` over tables with millions of rows, and the
     * generator is what keeps memory flat.
     *
     * @return Generator<int, \Duxbo\Seo\Data\SitemapUrl>
     */
    public function urls(): Generator;

    /**
     * Newest change in this source, for the sitemap index's `<lastmod>`.
     *
     * Should be answerable with a cheap aggregate — never by walking `urls()`.
     */
    public function lastModified(): ?DateTimeInterface;

    public function enabled(): bool;
}
