<?php

declare(strict_types=1);

namespace Duxbo\Seo\Sitemap;

use DateTimeInterface;
use Duxbo\Seo\Contracts\SitemapSource;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SitemapUrl;
use Duxbo\Seo\Events\SitemapUrlAdded;
use Duxbo\Seo\Support\SiteIndexability;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Assembles the sitemap index and the per-source files beneath it.
 *
 * Sources are registered explicitly; nothing is discovered.
 */
final class SitemapGenerator
{
    /** @var list<SitemapSource> */
    private array $sources = [];

    public function __construct(
        private readonly Config $config,
        private readonly UrlGenerator $urls,
        private readonly Dispatcher $events,
        private readonly SiteIndexability $indexability,
    ) {
    }

    public function register(SitemapSource $source): self
    {
        $this->sources[] = $source;

        return $this;
    }

    public function remove(string $name): self
    {
        $this->sources = array_values(array_filter(
            $this->sources,
            static fn (SitemapSource $source): bool => $source->name() !== $name,
        ));

        return $this;
    }

    /**
     * Enabled sources — empty whenever the site as a whole should not be
     * indexed. That single check governs both the HTTP sitemap routes and
     * `seo:sitemap`, so a demo domain with `seo.enabled = false` never
     * publishes a sitemap either, not only a noindex meta tag: listing URLs
     * search engines have just been told not to index is its own
     * contradiction, the same class of mistake as a stale robots.txt.
     *
     * @return list<SitemapSource>
     */
    public function sources(): array
    {
        if (! $this->indexability->ok()) {
            return [];
        }

        return array_values(array_filter(
            $this->sources,
            static fn (SitemapSource $source): bool => $source->enabled(),
        ));
    }

    public function source(string $name): ?SitemapSource
    {
        foreach ($this->sources() as $source) {
            if ($source->name() === $name) {
                return $source;
            }
        }

        return null;
    }

    /**
     * The index, listing one entry per source file.
     *
     * A source over the URL limit is split, and every part is listed, so the
     * index stays correct without the generator having to count the rows first.
     */
    public function index(): string
    {
        $writer = SitemapWriter::toMemory()->startIndex();

        foreach ($this->sources() as $source) {
            foreach ($this->partsOf($source) as $part) {
                $writer->writeSitemapReference(
                    $this->urlFor($source->name(), $part),
                    $source->lastModified(),
                );
            }
        }

        return $writer->finish();
    }

    /**
     * One source's URLs, as XML.
     *
     * @param  int  $part  1-based; parts beyond the first hold the overflow.
     */
    public function forSource(SitemapSource $source, int $part = 1): string
    {
        $writer = SitemapWriter::toMemory()->startUrlSet(
            alternates: $this->hasAlternates(),
            images: true,
        );

        foreach ($this->urlsForPart($source, $part) as $url) {
            $writer->writeUrl($url);
        }

        return $writer->finish();
    }

    /**
     * Write every file to a directory, for sites large enough that generating
     * on request is not viable.
     *
     * @return list<string> Paths written.
     */
    public function writeTo(string $directory): array
    {
        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create sitemap directory [{$directory}].");
        }

        $written = [];
        $directory = rtrim($directory, '/');

        foreach ($this->sources() as $source) {
            foreach ($this->partsOf($source) as $part) {
                $path = $directory.'/'.$this->filenameFor($source->name(), $part);

                $writer = SitemapWriter::toFile($path)->startUrlSet(
                    alternates: $this->hasAlternates(),
                    images: true,
                );

                foreach ($this->urlsForPart($source, $part) as $url) {
                    $writer->writeUrl($url);
                }

                $writer->finish();
                $written[] = $path;
            }
        }

        $indexPath = $directory.'/sitemap.xml';
        file_put_contents($indexPath, $this->index());
        $written[] = $indexPath;

        return $written;
    }

    public function maxUrls(): int
    {
        // The protocol caps a file at 50,000 URLs and 50MB uncompressed.
        $configured = (int) $this->config->get('seo.sitemap.max_urls', 50000);

        return max(1, min($configured, 50000));
    }

    public function urlFor(string $name, int $part = 1): string
    {
        return $this->urls->absolute('/'.$this->filenameFor($name, $part));
    }

    private function filenameFor(string $name, int $part = 1): string
    {
        return $part > 1
            ? "sitemap-{$name}-{$part}.xml"
            : "sitemap-{$name}.xml";
    }

    /**
     * URLs belonging to one part, with the event filter applied.
     *
     * @return \Generator<int, SitemapUrl>
     */
    private function urlsForPart(SitemapSource $source, int $part): \Generator
    {
        $max = $this->maxUrls();
        $skip = ($part - 1) * $max;
        $taken = 0;
        $seen = 0;

        foreach ($source->urls() as $url) {
            $event = new SitemapUrlAdded($source, $url);
            $this->events->dispatch($event);

            if ($event->excluded) {
                continue;
            }

            $url = $event->url;

            if ($seen++ < $skip) {
                continue;
            }

            if ($taken >= $max) {
                return;
            }

            $taken++;

            yield $url;
        }
    }

    /**
     * How many files this source needs.
     *
     * Counting means walking the source once. That is the price of not
     * requiring every source to be able to count itself cheaply, and it only
     * happens for the index.
     *
     * @return list<int>
     */
    private function partsOf(SitemapSource $source): array
    {
        $max = $this->maxUrls();
        $count = 0;

        foreach ($source->urls() as $ignored) {
            $count++;

            // Stop counting once the answer cannot change the file list much;
            // a source with more parts than this is misconfigured.
            if ($count > $max * 1000) {
                break;
            }
        }

        if ($count === 0) {
            return [];
        }

        return range(1, (int) ceil($count / $max));
    }

    private function hasAlternates(): bool
    {
        /** @var list<string> $supported */
        $supported = $this->config->get('seo.locales.supported', []);

        return count($supported) >= 2;
    }

    public function lastModified(): ?DateTimeInterface
    {
        $latest = null;

        foreach ($this->sources() as $source) {
            $candidate = $source->lastModified();

            if ($candidate !== null && ($latest === null || $candidate > $latest)) {
                $latest = $candidate;
            }
        }

        return $latest;
    }
}
