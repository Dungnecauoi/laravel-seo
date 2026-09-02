<?php

declare(strict_types=1);

namespace Duxbo\Seo\Sitemap\Sources;

use DateTimeInterface;
use Duxbo\Seo\Contracts\SitemapSource;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SitemapUrl;
use Duxbo\Seo\Enums\ChangeFrequency;
use Generator;

/**
 * Fixed URLs — the home page, about, contact.
 *
 * Static routes are never discovered automatically: a route list is full of
 * admin panels, webhooks and API endpoints that must not be advertised.
 */
final class RouteSource implements SitemapSource
{
    /**
     * @param  list<string|array<string, mixed>>  $entries  Paths, or ['url' => …, 'priority' => …].
     */
    public function __construct(
        private readonly array $entries,
        private readonly UrlGenerator $urls,
        private readonly string $name = 'pages',
        private readonly bool $enabled = true,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function enabled(): bool
    {
        return $this->enabled && $this->entries !== [];
    }

    /**
     * @return Generator<int, SitemapUrl>
     */
    public function urls(): Generator
    {
        foreach ($this->entries as $entry) {
            if (is_string($entry)) {
                yield new SitemapUrl(loc: $this->urls->absolute($entry));

                continue;
            }

            $loc = $entry['url'] ?? null;

            if (! is_string($loc)) {
                continue;
            }

            $frequency = $entry['changefreq'] ?? null;

            yield new SitemapUrl(
                loc: $this->urls->absolute($loc),
                changeFrequency: is_string($frequency) ? ChangeFrequency::tryFrom($frequency) : null,
                priority: isset($entry['priority']) ? (float) $entry['priority'] : null,
            );
        }
    }

    public function lastModified(): ?DateTimeInterface
    {
        return null;
    }
}
