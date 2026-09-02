<?php

declare(strict_types=1);

namespace Duxbo\Seo\Sitemap;

use DateTimeInterface;
use Duxbo\Seo\Data\SitemapUrl;
use XMLWriter;

/**
 * Writes sitemap XML one element at a time.
 *
 * XMLWriter rather than building a DOM or concatenating strings: a source over
 * a million-row table streams through here in constant memory, and every value
 * is escaped by the writer instead of by hand. Unescaped `&` in a query string
 * is the single most common way a sitemap ends up invalid.
 */
final class SitemapWriter
{
    private XMLWriter $writer;

    private int $count = 0;

    private bool $usesAlternates = false;

    private bool $usesImages = false;

    private function __construct(XMLWriter $writer)
    {
        $this->writer = $writer;
    }

    /**
     * Write to a file on disk.
     */
    public static function toFile(string $path): self
    {
        $writer = new XMLWriter();
        $writer->openUri($path);

        return new self($writer);
    }

    /**
     * Write straight to the response body, so nothing is held in memory.
     */
    public static function toOutput(): self
    {
        $writer = new XMLWriter();
        $writer->openUri('php://output');

        return new self($writer);
    }

    public static function toMemory(): self
    {
        $writer = new XMLWriter();
        $writer->openMemory();

        return new self($writer);
    }

    /**
     * @param  bool  $alternates  Declare the xhtml namespace for hreflang links.
     * @param  bool  $images  Declare the image namespace.
     */
    public function startUrlSet(bool $alternates = false, bool $images = false): self
    {
        $this->usesAlternates = $alternates;
        $this->usesImages = $images;

        $this->writer->startDocument('1.0', 'UTF-8');
        $this->writer->setIndent(true);
        $this->writer->startElement('urlset');
        $this->writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        // Namespaces are declared up front because they must sit on the root
        // element; a source cannot add one once URLs are streaming.
        if ($alternates) {
            $this->writer->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        }

        if ($images) {
            $this->writer->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        }

        return $this;
    }

    public function startIndex(): self
    {
        $this->writer->startDocument('1.0', 'UTF-8');
        $this->writer->setIndent(true);
        $this->writer->startElement('sitemapindex');
        $this->writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $this;
    }

    public function writeUrl(SitemapUrl $url): self
    {
        $this->writer->startElement('url');
        $this->writer->writeElement('loc', $url->loc);

        if ($url->lastModified !== null) {
            $this->writer->writeElement('lastmod', $url->lastModified->format(DateTimeInterface::ATOM));
        }

        if ($url->changeFrequency !== null) {
            $this->writer->writeElement('changefreq', $url->changeFrequency->value);
        }

        if ($url->priority !== null) {
            $this->writer->writeElement('priority', number_format($url->priority, 1, '.', ''));
        }

        if ($this->usesAlternates) {
            foreach ($url->alternates as $locale => $href) {
                $this->writer->startElement('xhtml:link');
                $this->writer->writeAttribute('rel', 'alternate');
                $this->writer->writeAttribute('hreflang', (string) $locale);
                $this->writer->writeAttribute('href', $href);
                $this->writer->endElement();
            }
        }

        if ($this->usesImages) {
            foreach ($url->images as $image) {
                $this->writer->startElement('image:image');
                $this->writer->writeElement('image:loc', $image);
                $this->writer->endElement();
            }
        }

        $this->writer->endElement();

        $this->count++;

        // Flushing keeps the buffer bounded; without it XMLWriter accumulates
        // the whole document even when writing to a stream.
        $this->writer->flush(false);

        return $this;
    }

    public function writeSitemapReference(string $loc, ?DateTimeInterface $lastModified = null): self
    {
        $this->writer->startElement('sitemap');
        $this->writer->writeElement('loc', $loc);

        if ($lastModified !== null) {
            $this->writer->writeElement('lastmod', $lastModified->format(DateTimeInterface::ATOM));
        }

        $this->writer->endElement();
        $this->writer->flush(false);

        return $this;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * Close the document. Returns the XML when writing to memory.
     */
    public function finish(): string
    {
        $this->writer->endElement();
        $this->writer->endDocument();

        return (string) $this->writer->flush(true);
    }
}
